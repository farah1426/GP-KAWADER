# STEP 2 (later): a small web service so the PHP project can call the parser.
# Run it with:   uvicorn api:app --port 8000
import os
from typing import Optional
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from cv_parsing import process_cv

app = FastAPI()
UPLOADS_DIR = os.environ.get("CV_UPLOADS_DIR")   # the folder where PHP saves the CVs (security: only read from it)

class CvRequest(BaseModel):
    pdf_path: str                  # the path of the PDF that PHP already saved
    full_name: Optional[str] = None

@app.post("/api/cv/process")
def process(request: CvRequest):
    path = os.path.realpath(request.pdf_path)
    if UPLOADS_DIR and not path.startswith(os.path.realpath(UPLOADS_DIR) + os.sep):
        raise HTTPException(status_code=400, detail="Path not allowed")
    if not path.lower().endswith(".pdf") or not os.path.isfile(path):
        raise HTTPException(status_code=404, detail="PDF not found")
    return process_cv(path, request.full_name)
