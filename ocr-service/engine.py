"""OCR engine abstraction.

Keeps the HTTP contract in `main.py` stable while letting us swap the
underlying engine. Today: Tesseract 5 + `ind` via pytesseract. Tomorrow:
RapidOCR (ONNX). The contract is purely "bytes in -> raw_text out".
"""

from __future__ import annotations

import base64
import io
import os
import shutil
from typing import Protocol

import requests
from PIL import Image, ImageOps

import pytesseract

# Common UB-Mannheim installer locations on Windows. pytesseract shells out to
# the `tesseract` binary, so if it isn't on PATH we must point at it explicitly
# or every scan fails with TesseractNotFoundError (HTTP 500 back to Laravel).
_WINDOWS_TESSERACT_PATHS = (
    r"C:\Program Files\Tesseract-OCR\tesseract.exe",
    r"C:\Program Files (x86)\Tesseract-OCR\tesseract.exe",
)


def _configure_tesseract_cmd() -> None:
    """Make pytesseract find the Tesseract binary regardless of PATH.

    Resolution order:
    1. ``TESSERACT_CMD`` env var (explicit override, any OS).
    2. ``tesseract`` already on PATH (the Linux/macOS/Docker happy path).
    3. Known Windows install locations (UB-Mannheim build).

    A no-op when the binary is already discoverable, so it stays safe on the
    production Linux VPS where Tesseract is installed via apt.
    """
    explicit = os.environ.get("TESSERACT_CMD", "").strip()
    if explicit:
        pytesseract.pytesseract.tesseract_cmd = explicit
        return

    if shutil.which("tesseract"):
        return

    for candidate in _WINDOWS_TESSERACT_PATHS:
        if os.path.exists(candidate):
            pytesseract.pytesseract.tesseract_cmd = candidate
            return


class OcrEngine(Protocol):
    """Anything that turns image bytes into raw OCR text."""

    name: str

    def recognize(self, image_bytes: bytes) -> str:  # pragma: no cover - protocol
        ...


class TesseractEngine:
    """Tesseract 5 with the Indonesian language pack.

    Footprint ~10 MB, ~1s/page on weak CPU. Good for clean printed struk.
    For photographed/skewed struk, swap to RapidOCR (see rule 03).
    """

    name = "tesseract"

    def __init__(self, lang: str = "ind") -> None:
        self.lang = lang
        _configure_tesseract_cmd()

    def recognize(self, image_bytes: bytes) -> str:
        with Image.open(io.BytesIO(image_bytes)) as img:
            # Auto-rotate using EXIF, then convert to RGB for consistency.
            img = ImageOps.exif_transpose(img)
            if img.mode != "RGB":
                img = img.convert("RGB")
            return pytesseract.image_to_string(img, lang=self.lang)


class GeminiEngine:
    """Cloud fallback via Google Gemini's vision API.

    Not the default (rule 03: Tesseract is the free, offline-first engine).
    This only runs when `GEMINI_API_KEY` is set AND the primary engine raised
    (e.g. Tesseract binary missing, or recognition errored) — see
    `build_fallback_engine` and its use in `main.py`.
    """

    name = "gemini"

    _ENDPOINT = "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent"
    _PROMPT = (
        "Transcribe every line of text visible on this Indonesian retail "
        "receipt (struk) exactly as printed, preserving line breaks and "
        "number formatting (e.g. 25.000, not 25000 or 25,000). "
        "Return plain text only, no commentary."
    )

    def __init__(self, api_key: str, model: str = "gemini-2.0-flash") -> None:
        self.api_key = api_key
        self.model = model

    def recognize(self, image_bytes: bytes) -> str:
        response = requests.post(
            self._ENDPOINT.format(model=self.model),
            params={"key": self.api_key},
            json={
                "contents": [
                    {
                        "parts": [
                            {"text": self._PROMPT},
                            {
                                "inline_data": {
                                    "mime_type": "image/jpeg",
                                    "data": base64.b64encode(image_bytes).decode("ascii"),
                                }
                            },
                        ]
                    }
                ]
            },
            timeout=20,
        )
        response.raise_for_status()
        data = response.json()
        return data["candidates"][0]["content"]["parts"][0]["text"]


def build_engine() -> OcrEngine:
    """Build the primary engine declared in OCR_ENGINE env (default: tesseract).

    Adding a new engine = add an `elif` and ship the import inside the
    branch so optional deps stay optional.
    """
    name = os.environ.get("OCR_ENGINE", "tesseract").strip().lower()

    if name == "tesseract":
        return TesseractEngine(lang=os.environ.get("OCR_LANG", "ind"))

    # Placeholder for the planned upgrade. Keeping it here documents the
    # swap point without dragging the dep into requirements.txt yet.
    if name == "rapidocr":  # pragma: no cover - not installed by default
        raise RuntimeError(
            "rapidocr engine not installed; pip install rapidocr-onnxruntime "
            "and wire a RapidOcrEngine class in engine.py"
        )

    raise RuntimeError(f"Unknown OCR_ENGINE: {name!r}")


def build_fallback_engine() -> OcrEngine | None:
    """Build the optional Gemini fallback, or None if not configured.

    Opt-in only: unset `GEMINI_API_KEY` and the service stays fully local +
    offline, matching rule 03. Set it to let `/ocr` retry against Gemini
    when the primary engine (Tesseract) fails.
    """
    api_key = os.environ.get("GEMINI_API_KEY", "").strip()
    if not api_key:
        return None

    return GeminiEngine(api_key=api_key, model=os.environ.get("GEMINI_MODEL", "gemini-2.0-flash"))
