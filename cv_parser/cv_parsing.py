# Kawader - CV parsing + sensitive information removal (standalone experiment)
#
# Main function:  process_cv(pdf_path, full_name=None)  ->  dict
# Steps:  1) read the PDF text in reading order (column aware)
#         2) remove sensitive information
#         3) split the CV into sections (headings, then strict boundaries)
#         4) clean each section with its OWN rules (skills are not education, etc.)
#
# The same function is used later inside the project (nothing to rewrite).
#
# WHY THE OLD VERSION MIXED THE SECTIONS (fixed in this file):
#   1. Blocks were sorted by (y, x) only, so in a two-column CV the lines of the left and the
#      right column were interleaved and a heading in one column "captured" the other column.
#   2. A heading was recognised only if it was in a short fixed word list. An unknown heading
#      ("Core Competencies", "Education & Training") was treated as normal text, so its content
#      leaked into the section above it.
#   3. A section never ended: it stayed open until the next KNOWN heading.
#   4. Every section was cleaned in the same way (one line = one item), so a paragraph that
#      leaked into "skills" was returned as a "skill".
#
# Optional: spaCy (model en_core_web_sm) is used as extra evidence when it is installed.
# Everything also works without it (pure regex), only slightly less accurate.

import difflib
import re
import statistics
from collections import Counter
from dataclasses import dataclass

import pymupdf   # PyMuPDF: reads the text of a PDF

try:                                    # spaCy is optional (see get_nlp)
    import spacy
except ImportError:                     # pragma: no cover
    spacy = None


# =====================================================================================
# 0) spaCy helper (optional)
# =====================================================================================
_NLP = {"model": None, "tried": False}


def get_nlp():
    """Load spaCy once. Returns None when spaCy or the model is not installed."""
    if not _NLP["tried"]:
        _NLP["tried"] = True
        if spacy is not None:
            try:   # the parser and lemmatizer are not needed (faster start)
                _NLP["model"] = spacy.load("en_core_web_sm", disable=["parser", "lemmatizer"])
            except Exception:
                _NLP["model"] = None
    return _NLP["model"]


# =====================================================================================
# 1) Read the PDF  (reading order, two columns)
# =====================================================================================
@dataclass
class Line:
    """One visual line of the PDF with its position and font (used for headings + columns)."""
    text: str
    size: float = 0.0          # font size (0 = unknown, e.g. text without a PDF)
    bold: bool = False
    page: int = 0
    x0: float = 0.0
    y0: float = 0.0
    x1: float = 0.0
    y1: float = 0.0
    col_start: bool = False    # first line of a column in a two-column band (a section cannot continue from the previous column)


PAGE_NUMBER = re.compile(r"^\s*(?:page\s*)?\d{1,2}(?:\s*(?:/|of)\s*\d{1,2})?\s*$", re.I)


def _is_bold(span):
    return bool(span["flags"] & 16) or "bold" in span["font"].lower() or "black" in span["font"].lower()


def _pieces_of_line(line, page_no):
    """A PDF line is cut into pieces where there is a big horizontal gap (e.g. two columns that
    PyMuPDF put on the same baseline). Pieces are put together again later, per column."""
    spans = sorted((s for s in line["spans"] if s["text"].strip()), key=lambda s: s["bbox"][0])
    groups, current = [], []
    for span in spans:
        if current and span["bbox"][0] - current[-1]["bbox"][2] > max(18, 2.0 * span["size"]):
            groups.append(current)
            current = []
        current.append(span)
    if current:
        groups.append(current)
    pieces = []
    for group in groups:
        text = re.sub(r"[-]", "•", "".join(s["text"] for s in group))   # symbol-font bullets
        text = re.sub(r"\s+", " ", text.replace(" ", " ")).strip()
        if not text:
            continue
        bold_chars = sum(len(s["text"]) for s in group if _is_bold(s))
        pieces.append(Line(
            text=text,
            size=round(max(s["size"] for s in group), 1),
            bold=bold_chars >= 0.5 * sum(len(s["text"]) for s in group),
            page=page_no,
            x0=min(s["bbox"][0] for s in group), y0=min(s["bbox"][1] for s in group),
            x1=max(s["bbox"][2] for s in group), y1=max(s["bbox"][3] for s in group)))
    return pieces


def _merge_rows(pieces):
    """Pieces on the same visual row (same baseline) become one line, left to right.
    Used for ONE column at a time, so the other column can never be mixed in."""
    rows = []
    for p in sorted(pieces, key=lambda p: (p.y0, p.x0)):
        if rows:
            last = rows[-1][-1]
            if abs((p.y0 + p.y1) / 2 - (last.y0 + last.y1) / 2) < 0.5 * min(p.y1 - p.y0, last.y1 - last.y0):
                rows[-1].append(p)
                continue
        rows.append([p])
    merged = []
    for row in rows:
        row.sort(key=lambda p: p.x0)
        first = row[0]
        merged.append(Line(
            text="  ".join(p.text for p in row), size=max(p.size for p in row), bold=first.bold, page=first.page,
            x0=first.x0, y0=min(p.y0 for p in row), x1=max(p.x1 for p in row), y1=max(p.y1 for p in row)))
    return merged


def _is_column(group):
    """A real column has stacked lines (3+ lines one under the other). Right-aligned dates
    (one per job) are not stacked, so they are not mistaken for a column."""
    if len(group) < 4:
        return False
    group = sorted(group, key=lambda l: l.y0)
    stacked, run = 0, 1
    for prev, cur in zip(group, group[1:]):
        if cur.y0 - prev.y1 < 2.2 * max(prev.size, 8):
            run += 1
        else:
            stacked += run if run >= 3 else 0
            run = 1
    stacked += run if run >= 3 else 0
    return stacked >= 4 and stacked >= 0.5 * len(group)


def find_gutter(pieces, page_width):
    """x position of the empty vertical space between two columns, or None (one column).
    A few full-width lines (name, contact line) are allowed to cross the gutter."""
    if len(pieces) < 8:
        return None
    max_crossing = max(2, int(0.1 * len(pieces)))
    valid = []
    x = page_width * 0.2
    while x <= page_width * 0.8:
        crossing = sum(1 for l in pieces if l.x0 < x - 1 and l.x1 > x + 1)
        if crossing <= max_crossing:
            left = [l for l in pieces if l.x1 <= x + 1]
            right = [l for l in pieces if l.x0 >= x - 1]
            if _is_column(_merge_rows(left)) and _is_column(_merge_rows(right)):
                valid.append((x, crossing))
        x += 2
    if not valid:
        return None
    runs, run = [], [valid[0]]                       # group neighbouring valid x values
    for item in valid[1:]:
        if item[0] - run[-1][0] <= 2.01:
            run.append(item)
        else:
            runs.append(run)
            run = [item]
    runs.append(run)
    best = min(runs, key=lambda r: (min(c for _, c in r), -len(r)))   # fewest crossing lines, then widest gap
    if len(best) < 4:                                # gap narrower than ~8 pt: not a real gutter
        return None
    return (best[0][0] + best[-1][0]) / 2


def order_page(pieces, page_width):
    """Reading order of one page: single column = top to bottom; two columns = left column
    then right column (full-width lines such as the name stay where they are)."""
    gutter = find_gutter(pieces, page_width)
    if gutter is None:
        return _merge_rows(pieces)
    ordered, band = [], []

    def flush():
        left = _merge_rows([p for p in band if p.x1 <= gutter + 1])
        right = _merge_rows([p for p in band if p.x1 > gutter + 1])
        if left and right:                           # a real two-column band
            left[0].col_start = right[0].col_start = True
        ordered.extend(left + right)
        band.clear()

    for p in sorted(pieces, key=lambda p: (p.y0, p.x0)):
        if p.x0 < gutter - 1 and p.x1 > gutter + 1:  # full-width line: closes the band above it
            flush()
            ordered.extend(_merge_rows([p]))
        else:
            band.append(p)
    flush()
    return ordered


def extract_lines(pdf_path):
    """All lines of the PDF in reading order, with font information."""
    lines = []
    with pymupdf.open(pdf_path) as pdf:
        for page_no, page in enumerate(pdf):
            pieces = []
            for block in page.get_text("dict")["blocks"]:
                if block.get("type") != 0:
                    continue
                for line in block["lines"]:
                    pieces.extend(_pieces_of_line(line, page_no))
            lines.extend(l for l in order_page(pieces, page.rect.width) if not PAGE_NUMBER.match(l.text))
    return lines


def extract_text(pdf_path):
    """The text of the PDF in reading order (one line per row)."""
    return "\n".join(l.text for l in extract_lines(pdf_path))


# =====================================================================================
# 2) Remove sensitive information
# =====================================================================================
# Lines like "Gender: Female" are removed completely
LABELED = re.compile(
    r"^\s*(?:full\s+name|name|gender|sex|nationality|date\s+of\s+birth|birth\s*date|dob|age|"
    r"marital\s+status|religion|address|national\s+id|iqama|passport(?:\s+no\.?)?)\s*[:\-–]\s*.+$",
    re.I | re.M)
EMAIL = re.compile(r"[\w.+-]+@[\w-]+(?:\.[\w-]+)+")
LINK = re.compile(r"(?:https?://|www\.|(?:linkedin|github|twitter|instagram|facebook)\.com/)\S+", re.I)
PHONE = re.compile(r"\+?\d[\d\s().-]{7,}\d")
BORN = re.compile(r"\bborn\b[^\n]{0,40}", re.I)


def remove_sensitive(text, full_name=None, guess_name=True):
    removed = {"name": 0, "email": 0, "phone": 0, "link": 0, "personal_lines": 0}

    def count(key):
        def replace(match):
            removed[key] += 1
            return ""
        return replace

    def phone_only(match):                       # a phone has at least 9 digits (so "2019 - 2023" stays)
        if len(re.sub(r"\D", "", match.group())) >= 9:
            removed["phone"] += 1
            return ""
        return match.group()

    text = LABELED.sub(count("personal_lines"), text)
    text = EMAIL.sub(count("email"), text)
    text = LINK.sub(count("link"), text)
    text = PHONE.sub(phone_only, text)
    text = BORN.sub(count("personal_lines"), text)

    # The name: use the real name if we have it (the project knows it from the user table),
    # otherwise the first line of the CV when it looks like a name (2-4 words).
    if full_name:
        parts = [full_name] + [p for p in full_name.split() if len(p) >= 3]
        for part in parts:
            text, n = re.subn(r"\b" + re.escape(part) + r"\b", "", text, flags=re.I)
            removed["name"] += n
    elif guess_name:
        first = next((l.strip() for l in text.split("\n") if l.strip()), "")
        if re.fullmatch(r"[A-Za-z.'-]+(?: [A-Za-z.'-]+){1,3}", first) and heading_section(first) is None:
            text = text.replace(first, "", 1)
            removed["name"] += 1

    text = re.sub(r"(?m)^[\s|•·,;]+|[\s|•·,;]+$", "", text)   # leftovers like "|  |" after the removal
    text = re.sub(r"[ \t]{2,}", " ", text)
    text = re.sub(r"[ \t]+\n", "\n", text)       # tidy empty spaces and lines
    text = re.sub(r"\n{3,}", "\n\n", text).strip()
    return text, removed


# =====================================================================================
# 3) Split into sections
# =====================================================================================
# A heading is recognised by its WORDS ("anchors"), not by an exact list of phrases, so
# "Technical Skills", "Core Competencies", "Education & Training", "Employment History",
# "Academic Background" ... all work. Add a word here when a CV is not recognised.
SECTIONS = ["summary", "skills", "education", "experience", "certifications", "languages", "projects"]

STRONG = {   # a word that clearly names a section
    "summary":        {"summary", "profile", "objective", "objectives", "about", "statement", "نبذة", "الملخص", "الهدف"},
    "skills":         {"skills", "skill", "competencies", "competency", "expertise", "proficiencies", "technologies",
                       "tools", "frameworks", "programming", "المهارات", "مهارات", "الكفاءات"},
    "education":      {"education", "educational", "qualifications", "qualification", "studies", "التعليم", "المؤهلات",
                       "التعليمي", "الدراسة"},
    "experience":     {"experience", "experiences", "employment", "internship", "internships", "apprenticeship",
                       "الخبرات", "الخبرة", "خبرات", "خبرة"},
    "certifications": {"certifications", "certification", "certificates", "certificate", "licenses", "licences",
                       "license", "courses", "course", "training", "trainings", "workshops", "الشهادات", "الدورات",
                       "الشهادة"},
    "languages":      {"languages", "language", "اللغات", "لغات"},
    "projects":       {"projects", "project", "المشاريع", "مشاريع"},
    "other":          {"references", "referees", "interests", "interest", "hobbies", "awards", "honors", "honours",
                       "achievements", "accomplishments", "publications", "volunteering", "volunteer", "activities",
                       "extracurricular", "contact", "information", "details", "declaration", "links", "social",
                       "المراجع", "الهوايات", "الاهتمامات"},
}
MEDIUM = {"education": {"academic", "academics"}}                      # "Academic Background" but not "Academic Projects"
WEAK = {"experience": {"career", "history", "background"}}             # only when no stronger word is present
FILLERS = {"and", "of", "the", "my", "&", "professional", "technical", "relevant", "key", "core", "personal",
           "additional", "other", "selected", "work", "working", "related", "main", "computer", "it", "software",
           "soft", "hard", "interpersonal", "spoken", "written", "digital", "general", "in", "a", "me", "areas",
           "graduation", "final", "year", "side", "highlights", "العملية", "العلمية", "الوظيفي", "التقنية",
           "الشخصية", "عني", "و"}
SKILLS_ONLY = {"frameworks", "tools", "technologies", "programming"}   # "Languages & Frameworks" is skills

_TIERS = []
for _tier, _table in enumerate((STRONG, MEDIUM, WEAK)):
    for _section, _words in _table.items():
        _TIERS.extend((w, _section, _tier) for w in _words)
ANCHOR = {w: (s, t) for w, s, t in _TIERS}
ALLOWED = set(ANCHOR) | FILLERS | {"&"}
VOCAB_PHRASES = sorted({w for w in ANCHOR if len(w) >= 5 and w.isascii()} |
                       {"work experience", "professional experience", "technical skills", "employment history",
                        "academic background", "professional summary", "education and training"})


def _heading_tokens(text):
    return re.findall(r"[^\W\d_]+|&", text.lower())


def _resolve(tokens):
    """The section named by the words of a heading (strong words first, then medium, then weak)."""
    hits = [(ANCHOR[t][1], t) for t in tokens if t in ANCHOR]
    if not hits:
        return None
    best_tier = min(ANCHOR[t][1] for t in tokens if t in ANCHOR)
    section = next(ANCHOR[t][0] for t in tokens if t in ANCHOR and ANCHOR[t][1] == best_tier)
    if section == "languages" and SKILLS_ONLY & set(tokens):
        section = "skills"
    return section


def heading_section(text, style_hint=False):
    """Section name ('skills', 'education', ... or 'other') when `text` is a heading, else None.
    `style_hint` = the line looks like a heading (bold / capitals / colon); only then a
    misspelled heading ("Experiance") is accepted."""
    text = text.strip().rstrip(":").strip()
    if not text or len(text) > 50 or re.search(r"[\d@.,;()]", text):
        return None
    tokens = _heading_tokens(text)
    if not tokens or len(tokens) > 6:
        return None
    if all(t in ALLOWED for t in tokens):
        return _resolve(tokens)
    if style_hint and len(tokens) <= 3:
        close = difflib.get_close_matches(" ".join(tokens), VOCAB_PHRASES, n=1, cutoff=0.86)
        if close:
            return _resolve(_heading_tokens(close[0]))
    return None


# "Skills: Python, SQL" (heading and content on the same line)
INLINE = re.compile(r"^\s*([^\W\d_][\w &/]{2,35}?)\s*:\s*(\S.*)$")
INLINE_SECTIONS = {"skills", "languages", "certifications", "education", "summary"}
STOP_LINE = re.compile(r"^\s*(?:references?\b|referees\b|i hereby declare|declaration\b)", re.I)
BULLET = re.compile(r"^[\s•·▪●■◦○▫►➢\-–—*]+")


def _style_sig(line):
    letters = re.sub(r"[^A-Za-z]", "", line.text)
    return (round(line.size), line.bold, bool(letters) and letters.isupper())


def _plain(line):
    return line if isinstance(line, Line) else Line(text=str(line))


def split_sections(lines):
    """Split the CV into sections. `lines` = list of Line (or plain text).
    Returns {section: [text lines]}.  A section ENDS at the next heading, at an unknown
    heading with the same look as the other headings, at a column break, or at 'References'."""
    if isinstance(lines, str):
        lines = lines.split("\n")
    lines = [_plain(l) for l in lines if _plain(l).text.strip()]
    has_style = any(l.size for l in lines)

    # Pass 1: the headings we recognise, and how the headings of THIS document look
    def looks_like_heading(l):
        t = l.text.strip()
        return t.isupper() or t.endswith(":") or l.bold
    kinds = [heading_section(l.text, looks_like_heading(l)) for l in lines]
    signature = Counter(_style_sig(l) for l, k in zip(lines, kinds) if k and has_style)
    heading_looks = {s for s, n in signature.items() if n >= 2}
    body_look = Counter(_style_sig(l) for l in lines).most_common(1)[0][0] if lines else None
    heading_looks.discard(body_look)

    found = {key: [] for key in SECTIONS}
    current = None
    for line, kind in zip(lines, kinds):
        text = line.text.strip()
        if line.col_start:
            current = None                                       # a new column never continues the old section
        if kind:                                                 # a heading (known words)
            current = kind                                       # 'other' = a section we do not extract
            continue
        if STOP_LINE.match(text):
            current = "other"
            continue
        if (has_style and _style_sig(line) in heading_looks and len(text.split()) <= 5
                and not re.search(r"[\d@.]", text)):             # unknown heading ("Hobbies Etc"): close the section
            current = "other"
            continue
        inline = INLINE.match(text)
        if inline:
            kind = heading_section(inline.group(1), True)
            content = inline.group(2).strip()
            if kind in INLINE_SECTIONS and (kind != "languages" or _is_language_list(content)):
                found[kind].append(content)
                if current in ("experience", "projects"):        # "Skills: ..." inside a job: one line only
                    continue
                current = kind
                continue
        if current in found:
            found[current].append(text)
    return found


# =====================================================================================
# 4) Clean each section (every section has its own rules)
# =====================================================================================
_MONTH = r"(?:jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[a-z]*\.?"
_MY = rf"(?:(?:{_MONTH}\s+|\d{{1,2}}/)?(?:19|20)\d{{2}})"
DATE_RANGE = re.compile(rf"\b{_MY}\s*(?:-|–|—|to|until)\s*(?:{_MY}|present|current|now|ongoing|today)\b", re.I)
YEAR = re.compile(r"\b(?:19|20)\d{2}\b")
DEGREE = re.compile(
    r"\b(?:bachelor(?:'s|s)?|master(?:'s|s)?\s+(?:of|in|degree)|master(?:'s|s)\b|doctorate|ph\.?d|diploma|"
    r"associate degree|degree|b\.?sc\.?|m\.?sc\.?|b\.?eng|b\.?tech|mba|b\.a\.|m\.a\.)\b|"
    r"\bhigh school\b|\bsecondary school\b|بكالوريوس|ماجستير|دكتوراه|دبلوم", re.I)
INSTITUTION = re.compile(r"\b(?:university|college|institute|school|academy|polytechnic|faculty)\b|جامعة|كلية|معهد", re.I)
ACADEMIC_DETAIL = re.compile(r"\b(?:gpa|cgpa|grade|honou?rs?|cum laude|first class|distinction|major|minor|thesis|"
                             r"dissertation|coursework|graduat\w*|expected|dean'?s list)\b", re.I)
JOB_TITLE = re.compile(r"\b(?:engineer|developer|analyst|manager|intern|specialist|consultant|administrator|designer|"
                       r"officer|assistant|coordinator|technician|director|teacher|accountant|supervisor|trainee|"
                       r"programmer|architect|researcher|executive|representative|lead)\b", re.I)
ORG_WORD = re.compile(r"\b(?:inc|ltd|llc|co|company|corp|corporation|group|technologies|solutions|systems|bank|"
                      r"hospital|ministry|telecom|services|labs?|agency|studio|consulting)\b\.?", re.I)
ACTION_VERBS = {
    "developed", "built", "managed", "led", "designed", "implemented", "created", "analysed", "analyzed", "maintained",
    "worked", "coordinated", "assisted", "supported", "improved", "reduced", "increased", "planned", "executed",
    "performed", "conducted", "collaborated", "participated", "delivered", "optimized", "optimised", "configured",
    "monitored", "trained", "wrote", "tested", "deployed", "handled", "organized", "organised", "prepared", "provided",
    "ensured", "achieved", "launched", "migrated", "cleaned", "supervised", "resolved", "integrated", "automated",
    "researched", "presented", "contributed", "established", "practiced", "practised", "applied", "utilized",
    "responsible", "experienced", "proficient", "seeking", "passionate", "interested", "familiar"}
WORK_VERBS = ACTION_VERBS - {"responsible", "experienced", "proficient", "seeking", "passionate", "interested", "familiar"}
SENTENCE_HINT = re.compile(r"\b(?:responsible for|worked (?:on|with|as)|experience (?:in|with|of)|using|proficient in|"
                           r"familiar with|knowledge of|ability to|in charge of|focused on|looking for|"
                           r"i am|i have|my )\b", re.I)

HUMAN_LANGUAGES = {
    "arabic", "english", "french", "german", "spanish", "italian", "portuguese", "russian", "chinese", "mandarin",
    "cantonese", "japanese", "korean", "hindi", "urdu", "bengali", "persian", "farsi", "turkish", "dutch", "swedish",
    "norwegian", "danish", "finnish", "polish", "greek", "hebrew", "indonesian", "malay", "tagalog", "filipino", "thai",
    "vietnamese", "swahili", "amharic", "kurdish", "pashto", "tamil", "telugu", "malayalam", "punjabi", "sinhala",
    "nepali", "somali", "hausa", "czech", "romanian", "hungarian", "ukrainian", "bulgarian", "serbian", "croatian"}
FRAGMENT_START = {"with", "and", "for", "in", "of", "to", "by", "on", "as", "at", "or", "the", "a", "an", "using", "including"}
LEVEL_WORDS = ("native", "mother tongue", "fluent", "advanced", "intermediate", "beginner", "basic", "good",
               "excellent", "professional", "proficient", "conversational", "elementary", "bilingual", "working")

# Well-known skill names: used ONLY to rescue skills from a sentence inside the skills section
TECH_TERMS = [
    "Python", "Java", "JavaScript", "TypeScript", "C++", "C#", "PHP", "SQL", "MySQL", "PostgreSQL", "MongoDB", "Oracle",
    "HTML", "CSS", "React", "Angular", "Vue", "Node.js", "Django", "Flask", "FastAPI", "Spring", "Laravel", "Git",
    "GitHub", "Docker", "Kubernetes", "Linux", "AWS", "Azure", "GCP", "Excel", "Power BI", "Tableau", "TensorFlow",
    "PyTorch", "scikit-learn", "Pandas", "NumPy", "OpenCV", "Machine Learning", "Deep Learning", "NLP", "Kotlin",
    "Swift", "Flutter", "Bash", "REST APIs", "Cisco", "Wireshark", "Photoshop", "Figma", "Jira", "Selenium"]
TECH_RE = re.compile(r"(?<![\w+#.])(" + "|".join(re.escape(t) for t in sorted(TECH_TERMS, key=len, reverse=True)) +
                     r")(?![\w+#])", re.I)
_TECH_CANON = {t.lower(): t for t in TECH_TERMS}


def _strip_bullet(raw):
    """-> (text without bullet, was it a bullet?)"""
    text = BULLET.sub("", raw)
    return re.sub(r"\s+", " ", text).strip(), bool(BULLET.match(raw)) and len(text) < len(raw)


def _word_count(text):
    return len(text.split())


def is_sentence_like(text, use_spacy=True):
    """True for a sentence / description (not a name or a short label). Regex first, spaCy as extra evidence."""
    words = text.split()
    n = len(words)
    ends_with_abbreviation = bool(re.search(r"\b(?:co|inc|ltd|corp|llc)\.$", text.strip(), re.I))   # "Tamkeen Co."
    if n >= 9 or (n >= 4 and text.rstrip().endswith(".") and not ends_with_abbreviation):
        return True
    if n >= 3 and (words[0].lower().strip(",.") in ACTION_VERBS or SENTENCE_HINT.search(text)):
        return True
    nlp = get_nlp() if use_spacy else None
    if nlp and n >= 4:                       # a finite verb in a 4+ word fragment = a sentence
        return any(t.tag_ in ("VBD", "VBZ", "VBP") for t in nlp(text))
    return False


def is_education_or_work_text(text):
    """Text that belongs to education or experience (so it can never be a skill / language / certificate)."""
    return bool(DEGREE.search(text) or INSTITUTION.search(text) or DATE_RANGE.search(text) or YEAR.search(text)
                or (JOB_TITLE.search(text) and re.search(r"\s(?:at|@|-|–|—|\|)\s|,", text)))


def _is_language_list(content):
    items = [i for i in re.split(r"[,;|/]", content) if i.strip()]
    names = [re.sub(r"\(.*?\)", "", i).strip().lower().split(" ")[0] for i in items]
    return bool(items) and sum(n in HUMAN_LANGUAGES for n in names) >= 0.6 * len(items)


def _dedupe(items):
    seen, out = set(), []
    for item in items:
        key = re.sub(r"[^\w+#]+", "", item.lower())      # keep + and # so "C++" and "C#" stay different
        if key and key not in seen:
            seen.add(key)
            out.append(item)
    return out


def _split_top_level(line):
    """Split on , ; | • and wide gaps, but not inside brackets: 'Office (Word, Excel)' stays together."""
    items, depth, buf = [], 0, ""
    for part in re.split(r"(\s{2,}|[,;|•·●▪■()])", line):
        if part == "(":
            depth += 1
        elif part == ")":
            depth = max(0, depth - 1)
        elif depth == 0 and (part.strip() == "" and len(part) >= 2 or part in ",;|•·●▪■"):
            items.append(buf)
            buf = ""
            continue
        buf += part
    items.append(buf)
    return [i.strip(" .:-–—") for i in items if i.strip(" .:-–—")]


# ---------- skills ----------
def clean_skills(lines, forbidden=frozenset()):
    """Skill NAMES only. Returns (skills, human_languages_found_in_the_skills_section).
    Rejected: sentences, dates, degrees, universities, job titles + employer, lines copied from another section."""
    skills, languages = [], []
    for raw in lines:
        text, _ = _strip_bullet(raw)
        label = re.match(r"^([^\W\d_][\w /&+#.]{1,30}):\s*", text)
        if label and ACADEMIC_DETAIL.search(label.group(1)):                 # "GPA: 4.5/5" is education
            continue
        text = re.sub(r"^[^\W\d_][\w /&+#.]{1,30}:\s*", "", text)           # "Tools: Git, Docker" -> "Git, Docker"
        if not text or re.sub(r"\W+", "", text.lower()) in forbidden:
            continue
        for item in _split_top_level(text):
            norm = re.sub(r"\W+", "", item.lower())
            if not norm or norm in forbidden or heading_section(item) is not None:
                continue
            if item.lower() in HUMAN_LANGUAGES:                              # "English" is a language, not a skill
                languages.append(item)
                continue
            max_words = 7 if "(" in item else 4
            first_word = item.split()[0]
            fragment = _word_count(item) >= 2 and (first_word.lower() in FRAGMENT_START or
                                                   (_word_count(item) >= 3 and first_word[0].islower() and first_word.isalpha()))
            sentence = fragment or is_sentence_like(item)
            if sentence or _word_count(item) > max_words or len(item) > 50:
                # Rescue known skill names from a statement like "Proficient in Python and SQL", but never from
                # a description of work done ("Developed APIs using Django") or from education text.
                if first_word.lower() not in WORK_VERBS and not is_education_or_work_text(item):
                    skills.extend(_TECH_CANON[m.lower()] for m in TECH_RE.findall(item))
                continue
            if (is_education_or_work_text(item) or re.search(r"@|https?:|www\.", item)
                    or not re.search(r"[^\W\d_]", item)):          # no letters ("4.5/5") is not a skill name
                continue
            skills.append(item)
    return _dedupe(skills), _dedupe(languages)


# ---------- languages ----------
def clean_languages(lines):
    """Spoken languages (with the level when written), nothing else."""
    out = []
    for raw in lines:
        text, _ = _strip_bullet(raw)
        for item in _split_top_level(re.sub(r"^[^\W\d_][\w ]{1,20}:\s*", "", text)):
            for part in re.split(r"\s*/\s*", item) if re.fullmatch(r"[A-Za-z ]+(?:/[A-Za-z ]+)+", item) else [item]:
                m = re.match(r"^([^\W\d_][^\W\d_ '-]*(?: [^\W\d_][^\W\d_'-]*)?)\s*(?:\((.+?)\)|[-–—:]\s*(.+))?$", part.strip())
                if not m:
                    continue
                name, level = m.group(1).strip(), (m.group(2) or m.group(3) or "").strip()
                known = name.lower().split(" ")[-1] in HUMAN_LANGUAGES or name.lower() in HUMAN_LANGUAGES
                if not known and (_word_count(name) > 1 or is_education_or_work_text(part) or name.lower() in _TECH_CANON
                                  or heading_section(name) is not None or name.lower() in LEVEL_WORDS):
                    continue                                                  # unknown AND not a clean single word
                if level and not any(w in level.lower() for w in LEVEL_WORDS) and not re.search(r"[abc][12]|\d", level, re.I):
                    level = ""
                out.append(f"{name.title() if name.isascii() else name} ({level})" if level else (name.title() if name.isascii() else name))
    by_name = {}                                  # one entry per language (the first one, usually with the level)
    for item in out:
        by_name.setdefault(item.split(" (")[0].lower(), item)
    return list(by_name.values())


# ---------- education ----------
def clean_education(lines):
    """One entry per degree: 'Degree | Institution | dates / GPA'. Entries without any academic signal are dropped."""
    entries, cur = [], []

    def flush():
        if cur and any(DEGREE.search(x) or INSTITUTION.search(x) or ACADEMIC_DETAIL.search(x) or YEAR.search(x)
                       for x in cur):
            entries.append(" | ".join(cur))
        cur.clear()

    for raw in lines:
        text, _ = _strip_bullet(raw)
        if not text or _word_count(text) > 25 or heading_section(text) is not None:
            continue                                         # a long paragraph is not a degree line
        new_degree = DEGREE.search(text) and any(DEGREE.search(x) for x in cur)
        new_school = INSTITUTION.search(text) and any(INSTITUTION.search(x) for x in cur)
        if new_degree or new_school:
            flush()
        cur.append(text)
    flush()
    return _dedupe(entries)


# ---------- experience ----------
def _is_date_only(text):
    rest = DATE_RANGE.sub("", text)
    rest = re.sub(rf"\b{_MY}\b|\b(?:present|current)\b", "", rest, flags=re.I)
    return bool(re.search(r"\d", text)) and not re.sub(r"[\s()\-–—,|/]+", "", rest)


def _looks_like_header(text):
    """A job header (title / employer / dates), not a sentence."""
    if _is_date_only(text):
        return True
    words = text.split()
    if len(words) > 12 or is_sentence_like(text):
        return False
    caps = sum(1 for w in words if w[:1].isupper() or w.lower() in {"and", "of", "at", "the", "for", "&", "-", "|"})
    if JOB_TITLE.search(text) or ORG_WORD.search(text) or DATE_RANGE.search(text) or re.search(r"\s(?:at|@|\||-|–|—)\s", text):
        return True
    nlp = get_nlp()
    if nlp and any(e.label_ in ("ORG", "DATE") for e in nlp(text).ents):
        return True
    return caps == len(words)


def clean_experience(lines):
    """One entry per job: 'Title - Employer | dates — what was done'. Education is never added here."""
    entries, head, desc = [], [], []

    def flush():
        if head or desc:
            text = " | ".join(head)
            if desc:
                text = (text + " — " if text else "") + " ".join(desc)
            entries.append(text)
        head.clear()
        desc.clear()

    for raw in lines:
        text, bullet = _strip_bullet(raw)
        if not text or heading_section(text) is not None:
            continue
        if bullet or text[0].islower() or not _looks_like_header(text):
            desc.append(text)                                # bullet / sentence / wrapped line
        elif desc and not desc[-1].rstrip().endswith((".", "!", "?", ";", ":")) and not (
                _is_date_only(text) or JOB_TITLE.search(text) or ORG_WORD.search(text)):
            desc.append(text)                                # a wrapped line of the description
        elif desc:                                           # description already started -> a new job
            flush()
            head.append(text)
        else:
            head.append(text)                                # still collecting title / employer / dates
    flush()
    return _dedupe(entries)


# ---------- projects ----------
def clean_projects(lines):
    """One entry per project: 'Name — description'."""
    entries, cur, kind = [], [], None

    def flush():
        if cur:
            entries.append(" — ".join(cur) if kind == "title" else " ".join(cur))
        cur.clear()

    for raw in lines:
        text, bullet = _strip_bullet(raw)
        if not text or heading_section(text) is not None:
            continue
        delimited = bool(re.search(r"\s[—–-]\s|:\s", text))
        title_only = (not bullet and not delimited and _word_count(text) <= 8 and not text.endswith((".", ",")) and
                      text[0].isupper())
        if cur and kind == "title" and not delimited:
            cur.append(text)                                 # description under a project title
        elif cur and (text[0].islower() or (not bullet and not delimited and not title_only
                                            and not cur[-1].endswith((".", "!", "?")))):
            cur[-1] = cur[-1] + " " + text                   # wrapped line
        else:
            flush()
            cur.append(text)
            kind = "title" if title_only else "item"
    flush()
    return _dedupe(entries)


# ---------- certifications ----------
def clean_certifications(lines):
    out = []
    for raw in lines:
        text, _ = _strip_bullet(raw)
        if not text or heading_section(text) is not None or _word_count(text) > 25:
            continue
        if re.match(r"^(?:bachelor|master|ph\.?d|doctorate|b\.?sc|m\.?sc)", text, re.I) or (
                INSTITUTION.search(text) and DEGREE.search(text)):
            continue                                         # a degree is not a certificate
        if out and (text[0].islower() or _is_date_only(text)):
            out[-1] = out[-1] + (" - " if _is_date_only(text) else " ") + text      # wrapped line / date on its own line
        else:
            out.append(text)
    return _dedupe(out)


# =====================================================================================
# All the steps together
# =====================================================================================
def _norm(text):
    return re.sub(r"\W+", "", text.lower())


def process_cv(pdf_path, full_name=None, debug=False):
    result = {"parse_status": "failed", "removed": {}}
    try:
        lines = extract_lines(pdf_path)
    except Exception:
        return result
    text = "\n".join(l.text for l in lines)
    if len(text.strip()) < 50:                       # a scanned CV (image): OCR can be added later
        return result

    clean, removed = remove_sensitive(text, full_name)

    # Sections are found on the ORIGINAL lines (they still have font and column information);
    # sensitive data is removed from every line before it is put in a section.
    safe_lines = []
    for l in lines:
        bullet = bool(BULLET.match(l.text))
        scrubbed, _ = remove_sensitive(l.text, full_name, guess_name=False)
        if scrubbed:
            safe_lines.append(Line(**{**l.__dict__, "text": ("• " if bullet else "") + scrubbed}))
    sections = split_sections(safe_lines)

    # Lines of the other sections: a skill can never be a copy of one of them
    other_lines = {_norm(_strip_bullet(x)[0]) for key in ("education", "experience", "projects", "certifications")
                   for x in sections[key] if len(x.split()) >= 2}
    skills, skill_languages = clean_skills(sections["skills"], forbidden=other_lines)
    languages = clean_languages(sections["languages"] + skill_languages)

    result.update({
        "skills":         skills,
        "education":      clean_education(sections["education"]),
        "experience":     clean_experience(sections["experience"]),
        "certifications": clean_certifications(sections["certifications"]),
        "languages":      languages,
        "projects":       clean_projects(sections["projects"]),
        "summary":        " ".join(_strip_bullet(x)[0] for x in sections["summary"]),
        "removed":        removed,
        "clean_text":     clean,                    # the CV text without sensitive information
    })
    result["parse_status"] = "success" if any(result[k] for k in ("skills", "education", "experience")) else "failed"
    if debug:
        result["original_text"] = text            # only for the test page: to compare with clean_text
        result["sections_raw"] = {k: v for k, v in sections.items() if v}
    return result
