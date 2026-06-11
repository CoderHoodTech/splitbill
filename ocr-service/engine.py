"""OCR engine abstraction.

Keeps the HTTP contract in `main.py` stable while letting us swap the
underlying engine. Today: Tesseract 5 + `ind` via pytesseract. Tomorrow:
RapidOCR (ONNX). The contract is purely "bytes in -> raw_text out".
"""

from __future__ import annotations

import io
import os
import shutil
from typing import Protocol

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


def build_engine() -> OcrEngine:
    """Build the engine declared in OCR_ENGINE env (default: tesseract).

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
