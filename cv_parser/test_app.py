# Test page: upload a CV (PDF) and see what the parser extracted.
# Run:   uvicorn test_app:app --port 8000      then open   http://localhost:8000
import base64
import html
import os
import tempfile
from fastapi import FastAPI, File, Form, UploadFile
from fastapi.responses import HTMLResponse
from cv_parsing import process_cv

app = FastAPI()

STYLE = """
body{margin:0;font-family:Segoe UI,Arial,sans-serif;background:#f1edf9;color:#1f1b2e}
header{background:linear-gradient(135deg,#1e0a45,#5b2cf0);color:#fff;padding:22px 40px}
header h1{margin:0;font-size:22px} header p{margin:4px 0 0;color:#d9ccff;font-size:14px}
main{max-width:1250px;margin:28px auto;padding:0 24px}
.box{background:#fff;border:1.5px solid #cdbff0;border-radius:16px;padding:22px;margin-bottom:20px}
h2{margin:0 0 12px;font-size:17px} h3{margin:18px 0 8px;font-size:15px;color:#5b21b6}
input[type=text]{padding:10px;border:1.5px solid #cdbff0;border-radius:10px;width:260px}
button{background:#5b21b6;color:#fff;border:0;border-radius:10px;padding:11px 24px;font-weight:700;cursor:pointer}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px} @media(max-width:900px){.grid{grid-template-columns:1fr}}
embed{width:100%;height:760px;border:1px solid #e4daf7;border-radius:10px}
.tag{display:inline-block;background:#f1ecfc;color:#4c1d95;padding:4px 11px;border-radius:8px;margin:0 6px 6px 0;font-size:13px;font-weight:600}
.ok{background:#e7f6ee;color:#0a7a3d}.bad{background:#fdecec;color:#b42318}.warn{background:#fef3c7;color:#b45309}
.chip{display:inline-block;padding:5px 12px;border-radius:999px;font-weight:700;font-size:13px;margin-right:8px}
ul{margin:0;padding-left:20px;line-height:1.7;font-size:14px} .empty{color:#b45309;font-size:14px}
pre{white-space:pre-wrap;background:#faf8ff;border:1px solid #e4daf7;border-radius:10px;padding:12px;font-size:13px;max-height:340px;overflow:auto;margin:0}
"""

def page(body):
    return f"""<!DOCTYPE html><html><head><meta charset="utf-8"><title>CV parser test</title><style>{STYLE}</style></head>
<body><header><h1>Kawader - CV parser test</h1><p>Upload a PDF and check what was extracted</p></header><main>{body}</main></body></html>"""

FORM = """<div class="box"><form method="post" enctype="multipart/form-data">
<p><input type="file" name="cv" accept=".pdf,application/pdf" required></p>
<p><input type="text" name="full_name" placeholder="Candidate's real name (optional)"></p>
<button type="submit">Submit</button></form></div>"""

def e(text):
    return html.escape(str(text))

def section(title, items, as_tags=False):
    if not items:
        return f"<h3>{title} (0)</h3><div class='empty'>Nothing found</div>"
    if as_tags:
        body = "".join(f"<span class='tag'>{e(i)}</span>" for i in items)
    else:
        body = "<ul>" + "".join(f"<li>{e(i)}</li>" for i in items) + "</ul>"
    return f"<h3>{title} ({len(items)})</h3>{body}"

def show(result, pdf_bytes):
    ok = result["parse_status"] == "success"
    status = f"<span class='chip {'ok' if ok else 'bad'}'>{e(result['parse_status'])}</span>"
    removed = " &nbsp; ".join(f"{k}: <b>{v}</b>" for k, v in result["removed"].items())
    pdf = base64.b64encode(pdf_bytes).decode()
    if not ok and "original_text" not in result:
        return f"<div class='box'>{status}<p>The text of this PDF could not be read (a scanned CV needs OCR).</p></div>"
    return f"""<div class="box">{status}<p style="margin:12px 0 0;font-size:14px">Removed from the CV: {removed}</p></div>
<div class="grid">
  <div class="box"><h2>The PDF</h2><embed src="data:application/pdf;base64,{pdf}" type="application/pdf"></div>
  <div class="box"><h2>What the parser extracted</h2>
    {section("Skills", result["skills"], as_tags=True)}
    {section("Education", result["education"])}
    {section("Experience", result["experience"])}
    {section("Certifications", result["certifications"])}
    {section("Languages", result["languages"], as_tags=True)}
    {section("Projects", result["projects"])}
    <h3>Summary</h3><div style="font-size:14px">{e(result["summary"]) or "<span class='empty'>Nothing found</span>"}</div>
  </div>
</div>
<div class="grid">
  <div class="box"><h2>Original text of the PDF</h2><pre>{e(result["original_text"])}</pre></div>
  <div class="box"><h2>After removing sensitive information</h2><pre>{e(result["clean_text"])}</pre></div>
</div>
<div class="box"><a href="/">&larr; Try another CV</a></div>"""

@app.get("/", response_class=HTMLResponse)
def home():
    return page(FORM)

@app.post("/", response_class=HTMLResponse)
async def check(cv: UploadFile = File(...), full_name: str = Form("")):
    data = await cv.read()
    if not data.startswith(b"%PDF") or len(data) > 5 * 1024 * 1024:
        return page("<div class='box'><span class='chip bad'>Please upload a PDF file (max 5 MB)</span></div>" + FORM)
    with tempfile.NamedTemporaryFile(suffix=".pdf", delete=False) as f:
        f.write(data)
    try:
        result = process_cv(f.name, full_name.strip() or None, debug=True)
    finally:
        os.remove(f.name)
    return page(show(result, data))
