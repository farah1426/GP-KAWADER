
import os
import re
from pathlib import Path

from fastapi import FastAPI, HTTPException
from pydantic import BaseModel, Field

from cv_parsing import process_cv

app = FastAPI()

UPLOADS_DIR = Path(
    os.environ["CV_UPLOADS_DIR"]
).resolve()

class CvRequest(BaseModel):
    file_name: str = Field(min_length=1, max_length=100)
    full_name: str | None = None


@app.post("/api/cv/process")
def process(request: CvRequest):
    if not re.fullmatch(r"[a-f0-9]{32}\.pdf", request.file_name):
        raise HTTPException(status_code=400, detail="Invalid file name")

    path = (UPLOADS_DIR / request.file_name).resolve()

    if path.parent != UPLOADS_DIR:
        raise HTTPException(status_code=400, detail="Invalid path")

    if not path.is_file() or path.suffix.lower() != ".pdf":
        raise HTTPException(status_code=404, detail="PDF not found")

    if path.stat().st_size > 5 * 1024 * 1024:
        raise HTTPException(status_code=413, detail="PDF too large")

    with path.open("rb") as pdf_file:
        signature = pdf_file.read(5)
    if signature != b"%PDF-":
        raise HTTPException(status_code=400, detail="Invalid PDF")

    return process_cv(str(path), request.full_name)
