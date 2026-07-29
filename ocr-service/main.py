"""FastAPI entrypoint for the SplitBill OCR microservice.

Bind to 127.0.0.1 only — Laravel calls this via HTTP. See rule 03 for the
JSON contract this endpoint must keep stable, and rule 04 for how it slots
into the local architecture.

    uvicorn main:app --host 127.0.0.1 --port 8001
"""

from __future__ import annotations

from fastapi import FastAPI, File, HTTPException, UploadFile

from engine import build_engine, build_fallback_engine
from parser import parse_receipt

MAX_UPLOAD_BYTES = 5 * 1024 * 1024  # Mirror Laravel-side limit (rule 03).
ALLOWED_MIME_PREFIX = "image/"

app = FastAPI(title="SplitBill OCR", version="1.0.0")
_engine = build_engine()
_fallback_engine = build_fallback_engine()


@app.get("/health")
def health() -> dict[str, str | None]:
    return {
        "status": "ok",
        "engine": _engine.name,
        "fallback_engine": _fallback_engine.name if _fallback_engine else None,
    }


@app.post("/ocr")
async def ocr(file: UploadFile = File(...)) -> dict:
    if not file.content_type or not file.content_type.startswith(ALLOWED_MIME_PREFIX):
        raise HTTPException(status_code=415, detail="File must be an image.")

    body = await file.read()
    if not body:
        raise HTTPException(status_code=400, detail="Empty upload.")
    if len(body) > MAX_UPLOAD_BYTES:
        raise HTTPException(status_code=413, detail="Image too large (max 5MB).")

    try:
        raw_text = _engine.recognize(body)
        engine_used = _engine.name
    except Exception as primary_exc:  # pragma: no cover - engine error path
        if _fallback_engine is None:
            raise HTTPException(status_code=500, detail=f"OCR failed: {primary_exc}") from primary_exc
        try:
            raw_text = _fallback_engine.recognize(body)
            engine_used = _fallback_engine.name
        except Exception as fallback_exc:
            raise HTTPException(
                status_code=500,
                detail=f"OCR failed: primary={primary_exc}; fallback={fallback_exc}",
            ) from fallback_exc

    parsed = parse_receipt(raw_text)

    return {
        "raw_text": raw_text,
        "total_guess": parsed.total_guess,
        "line_items": [{"name": i.name, "amount": i.amount} for i in parsed.line_items],
        "engine": engine_used,
    }
