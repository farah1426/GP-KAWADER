# Builds small synthetic CV PDFs (different layouts and headings) with PyMuPDF only.
# Used by the tests, and can also be run alone:  python tests/make_test_cvs.py samples_generated
import os
import sys
import pymupdf

H, B = "hebo", "helv"        # bold / regular fonts built into PyMuPDF


def _write(path, pages):
    """pages: list of lists of (x, y, text, font, size)."""
    doc = pymupdf.open()
    for items in pages:
        page = doc.new_page()
        for x, y, text, font, size in items:
            page.insert_text((x, y), text, fontname=font, fontsize=size)
    doc.save(path)
    doc.close()


class Col:
    """Writes lines one under the other inside a column."""
    def __init__(self, x, y=60, width=60):
        self.x, self.y, self.width, self.items = x, y, width, []

    def title(self, text, size=11):
        self.y += 10
        self.items.append((self.x, self.y, text, H, size))
        self.y += 16

    def line(self, text, size=9.5):
        self.items.append((self.x, self.y, text, B, size))
        self.y += 13

    def bold(self, text, size=10):
        self.items.append((self.x, self.y, text, H, size))
        self.y += 14

    def gap(self, n=6):
        self.y += n


# --- CV A: one column, "alternative" headings ------------------------------------------------
def cv_single_column(path):
    c = Col(50)
    c.items.append((50, 50, "Omar Hassan", H, 20)); c.y = 72
    c.line("omar.hassan@example.com | +966 55 123 4567 | Jeddah")
    c.title("PROFESSIONAL EXPERIENCE")
    c.bold("Software Developer - ABC Technologies")
    c.line("Jan 2022 - Present")
    c.line("- Developed REST APIs using Python and Django for the payments team.")
    c.line("- Managed a team of 3 engineers and improved deployment time by 40%.")
    c.bold("Junior Developer - XYZ Solutions")
    c.line("Jun 2020 - Dec 2021")
    c.line("- Built dashboards with JavaScript and SQL.")
    c.title("ACADEMIC BACKGROUND")
    c.bold("Bachelor of Computer Science")
    c.line("King Abdulaziz University, 2016 - 2020")
    c.line("GPA: 4.5/5")
    c.title("TECHNICAL SKILLS")
    c.line("Languages: Python, Java, SQL")
    c.line("Frameworks: Django, React, FastAPI")
    c.line("Tools: Git, Docker, Linux")
    c.title("CERTIFICATIONS")
    c.line("AWS Certified Cloud Practitioner - 2023")
    c.line("Google Data Analytics Certificate")
    c.title("PROJECTS")
    c.line("Smart Attendance System - Face recognition app built with Python and OpenCV.")
    c.title("LANGUAGES")
    c.line("Arabic (Native), English (Fluent)")
    _write(path, [c.items])


# --- CV B: two columns, sidebar on the left. Rows are interleaved vertically ---------------
def cv_two_columns_left_sidebar(path):
    left, right = Col(40, 60), Col(220, 60)
    left.items.append((40, 50, "Sara Ali", H, 20))
    left.y = 85
    left.line("sara.ali@example.com"); left.line("+966 50 000 1111"); left.line("Riyadh")
    left.title("SKILLS")
    for s in ["Python", "SQL", "Power BI", "Machine Learning", "Communication"]:
        left.line(s)
    left.title("LANGUAGES")
    left.line("English"); left.line("Arabic")
    left.title("CERTIFICATIONS")
    left.line("Azure Fundamentals AZ-900")
    right.y = 85
    right.title("WORK EXPERIENCE")
    right.bold("Data Analyst, Tamkeen Co.")
    right.line("2021 - Present")
    right.line("Analysed sales data and built reports for management.")
    right.line("Led the migration of reports from Excel to Power BI.")
    right.bold("Intern, Data Lab")
    right.line("2020 - 2021")
    right.line("Cleaned datasets and trained simple models in Python.")
    right.title("EDUCATION")
    right.bold("Master of Data Science")
    right.line("Imam University, 2019 - 2021")
    right.bold("Bachelor of Statistics")
    right.line("King Saud University, 2015 - 2019")
    right.title("PROJECTS")
    right.line("Churn Predictor - Logistic regression model for telecom churn.")
    _write(path, [left.items + right.items])


# --- CV C: two columns, sidebar on the RIGHT, different headings ----------------------------
def cv_two_columns_right_sidebar(path):
    main, side = Col(40, 60), Col(400, 60)
    main.items.append((40, 50, "Khalid Nasser", H, 20))
    main.y = 85
    main.title("EMPLOYMENT HISTORY")
    main.bold("Network Engineer | Saudi Telecom")
    main.line("03/2019 - 08/2023")
    main.line("Configured Cisco routers and monitored network performance.")
    main.line("Reduced downtime by 25% through proactive maintenance.")
    main.title("EDUCATION & TRAINING")
    main.bold("B.Sc. in Information Technology, Taibah University")
    main.line("Graduated 2018 with honors")
    main.title("COURSES & CERTIFICATES")
    main.line("CCNA - Cisco, 2019")
    side.y = 85
    side.title("CORE COMPETENCIES")
    for s in ["Networking", "Cisco IOS", "Firewalls", "Linux", "Python scripting"]:
        side.line(s)
    side.title("LANGUAGES")
    side.line("Arabic - Native"); side.line("English - Good")
    _write(path, [main.items + side.items])


# --- CV D: skills inline after the heading, mixed-case headings with colon, 2 pages ---------
def cv_inline_and_two_pages(path):
    c = Col(50)
    c.items.append((50, 50, "Lina Mahmoud", H, 18)); c.y = 70
    c.title("Profile")
    c.line("Marketing graduate with a passion for digital campaigns.")
    c.title("Skills:")
    c.line("SEO, Google Analytics, Content Writing, Adobe Photoshop")
    c.title("Work Experience:")
    c.bold("Marketing Specialist at BrightAds")
    c.line("2021 - 2024")
    c.line("Planned and executed social media campaigns that grew followers by 60%.")
    c.line("Coordinated with designers and wrote copy for newsletters.")
    p1 = c.items
    d = Col(50, 50)
    d.title("Education:")
    d.bold("Bachelor of Business Administration - Marketing")
    d.line("Effat University, 2017 - 2021")
    d.title("Projects:")
    d.line("Brand Relaunch - Led the rebranding campaign for a local cafe.")
    d.title("Languages:")
    d.line("Arabic, English, French")
    _write(path, [p1, d.items])


# --- CV E: an UNKNOWN heading right after the skills (must close the skills section) --------
def cv_unknown_heading_after_skills(path):
    c = Col(50)
    c.items.append((50, 50, "Fahad Salem", H, 20)); c.y = 72
    c.title("SKILLS")
    c.line("Python, SQL, Git")
    c.line("Excel, Power BI")
    c.title("COMMUNITY INVOLVEMENT")
    c.line("Organised a coding bootcamp for high school students in Riyadh every summer.")
    c.line("Volunteered at the national hackathon and mentored 20 participants.")
    c.title("Experiance")
    c.bold("Support Engineer, Zain KSA")
    c.line("2019 - 2022")
    c.line("Resolved customer tickets and wrote documentation for the support team.")
    c.title("EDUCATION")
    c.bold("Bachelor of Information Systems")
    c.line("Qassim University, 2015 - 2019")
    _write(path, [c.items])


# --- CV F: no headings at all (nothing must be invented) -------------------------------------
def cv_no_sections(path):
    c = Col(50)
    c.items.append((50, 50, "Nora Adel", H, 20)); c.y = 72
    for text in ["I am a graduate who enjoys learning new things and meeting people.",
                 "I worked in several places and I like Python, teamwork and long walks.",
                 "Please contact me to hear more about what I can offer your company in the future."]:
        c.line(text)
    _write(path, [c.items])


CVS = {
    "single_column": cv_single_column,
    "two_col_left": cv_two_columns_left_sidebar,
    "two_col_right": cv_two_columns_right_sidebar,
    "inline_two_pages": cv_inline_and_two_pages,
    "unknown_heading": cv_unknown_heading_after_skills,
    "no_sections": cv_no_sections,
}


def build_all(folder):
    os.makedirs(folder, exist_ok=True)
    paths = {}
    for name, fn in CVS.items():
        paths[name] = os.path.join(folder, name + ".pdf")
        fn(paths[name])
    return paths


if __name__ == "__main__":
    print(build_all(sys.argv[1] if len(sys.argv) > 1 else "tests/generated"))
