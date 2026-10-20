# Kawader - CV parsing + sensitive information removal (experiment)

Works on its own, outside the project. When it is good enough, copy it into the project (see "Moving it").

## Run it
```
pip install -r requirements.txt
python -m spacy download en_core_web_sm             # optional: better sentence detection (the parser also works without it)
python run.py samples/sample_cv.pdf                 # prints the result as JSON
python run.py samples/sample_cv.pdf "Sara Ali"      # with the real name (removes it from the whole CV)
```

## Test page (to check the result with your eyes)
```
uvicorn test_app:app --port 8000
```
Open http://localhost:8000, upload a PDF and press Submit. You see the PDF on the left and what the parser extracted on the right,
and below them the original text next to the text after the sensitive information was removed.

## Files
| File | What it does |
|---|---|
| `cv_parsing.py` | All the logic. Main function: `process_cv(pdf_path, full_name=None)` |
| `run.py` | Try it from the terminal |
| `test_app.py` | The test page (upload a PDF, see the result) |
| `api.py` | (step 2) small web service so PHP can call the parser |
| `samples/` | Put your test CVs here |
| `tests/` | `make_test_cvs.py` builds test CVs (one/two columns, different headings), `test_cv_parser.py` checks them: `python -m pytest tests -v` |

## How the sections are found (and kept apart)
1. **Reading order**: each PDF page is read line by line with its position and font. If the page has two columns (an empty vertical gap with stacked lines on both sides) the left column is read completely, then the right one. Full-width lines (name, contact) stay in place.
2. **Headings**: recognised by their words, not by an exact list: "Technical Skills", "Core Competencies", "Professional Experience", "Employment History", "Academic Background", "Education & Training", ... (small spelling mistakes too, and a few Arabic headings). Add a word to `STRONG` in `cv_parsing.py` if a CV is not recognised.
3. **Where a section ends**: at the next heading, at an UNKNOWN heading that looks like the other headings of the same CV (same size / bold / capitals), when a new column starts, and at "References". So content cannot leak into the section above it.
4. **Each section has its own cleaner**: skills = names only (sentences, dates, degrees, universities and job lines are rejected), education = one entry per degree, experience = one entry per job, languages = real spoken languages, ...
5. A section that is missing or uncertain gives an empty list.

## What `process_cv` returns (same names as the table `cv_parsed`)
```
skills, education, experience, certifications, languages, projects   -> lists of text
parse_status   "success" or "failed"          -> column parse_status
removed        how many name / email / phone / link / personal lines were removed
clean_text     the CV text without sensitive information (used later for embeddings)
```
In the database store each list with `json_encode(...)` in its TEXT column.

## How the sensitive information removal works
1. Labeled lines are deleted: Name, Gender, Nationality, Date of Birth, Age, Marital status, Religion, Address, National ID, Iqama, Passport.
2. Emails, phone numbers (9+ digits, so "2019 - 2023" stays), links (LinkedIn, GitHub, ...) and "Born ..." are removed.
3. The name is removed with `full_name` when we have it (the project has it in table `user`), otherwise the first line if it looks like a name.
4. The photo is never read, because only the text of the PDF is extracted.

Known limits: a nationality written inside a sentence is not detected; scanned CVs (images) need OCR; a CV with very unusual headings needs its words added to `SECTIONS` in `cv_parsing.py`.

## Moving it into the project (when it works)
1. **Copy** `cv_parsing.py` and `api.py` to a folder in the project, for example `ai/`.
2. **Run the service** next to Apache: `CV_UPLOADS_DIR=/path/to/kawader/uploads uvicorn api:app --port 8000`
3. **Call it from PHP** after the CV is saved (page `jobs.php` when the candidate applies). The result is saved once per CV and reused:
```php
// $cvId, $filePath (from table cv_file), $fullName (from table user)
$exists = $pdo->prepare("SELECT 1 FROM cv_parsed WHERE cv_id = ?");
$exists->execute([$cvId]);
if (!$exists->fetch()) {
    $ch = curl_init("http://127.0.0.1:8000/api/cv/process");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode(["pdf_path" => realpath(__DIR__ . "/../" . $filePath), "full_name" => $fullName]),
    ]);
    $r = json_decode(curl_exec($ch), true);
    $pdo->prepare("INSERT INTO cv_parsed (cv_id, skills, education, experience, certifications, languages, projects, parse_status)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$cvId, json_encode($r["skills"] ?? []), json_encode($r["education"] ?? []), json_encode($r["experience"] ?? []),
                   json_encode($r["certifications"] ?? []), json_encode($r["languages"] ?? []), json_encode($r["projects"] ?? []),
                   $r["parse_status"] ?? "failed"]);
}
```
4. In `jobs.php` the JavaScript function `processCV()` calls a small PHP file that runs step 3, and `submitApplication()` goes on as now.

Later steps use the same output: embeddings (`clean_text`), ranking and the explanation report.

## How to test it well (for the report)
Put 10-20 different CVs in `samples/` (different layouts), write the expected skills/education by hand for each, and count how many the parser finds. Report it per section.
