# Try the parser on a CV:   python run.py samples/sample_cv.pdf
#                           python run.py samples/sample_cv.pdf "Sara Ali"   (with the real name)
import json
import sys
from cv_parsing import process_cv

pdf_path = sys.argv[1]
full_name = sys.argv[2] if len(sys.argv) > 2 else None
print(json.dumps(process_cv(pdf_path, full_name), indent=2, ensure_ascii=False))
