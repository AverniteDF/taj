import os
import re
import difflib

# Define the directory containing the articles and the template file.
ARTICLES_DIR = 'articles'
TEMPLATE_FILE = 'template_article.html'
CHECK_CONTRIBUTORS = False

def extract_relevant_sections(html_lines):
    """
    Extract the relevant comparison sections:
    - Everything from the start of the file down to </head> (excluding <title> content).
    - Everything from <section class="author-info"> to the end of the file.
    """
    start_section = []
    end_section = []
    in_head = False
    in_title = False
    in_author_info = False

    for line in html_lines:
        stripped_line = line.strip()

        # Handle the head section (exclude <title> tag content)
        if '<head>' in stripped_line:
            in_head = True
        if '</head>' in stripped_line:
            start_section.append(line)
            in_head = False

        if in_head:
            if '<title>' in stripped_line:
                in_title = True
            if '</title>' in stripped_line:
                in_title = False
                continue  # Skip the entire <title> line
            if not in_title:
                start_section.append(line)

        # Handle the author-info section at the end
        if '<section class="author-info">' in stripped_line:
            in_author_info = True

        if in_author_info:
            if '<span class="contributors">' in stripped_line and not CHECK_CONTRIBUTORS:
                continue # Skip the entire contributors line

        if in_author_info:
            end_section.append(line)

    return start_section, end_section

def compare_sections(article_file, section_name, article_section, template_section):
    """
    Compare two sections of the article and template. If a mismatch is found, return the line number and snippet.
    """
    diff = list(difflib.unified_diff(article_section, template_section, lineterm=''))
    if diff:
        print(f"Mismatch found in {section_name} of {article_file}:")
        # Print the first few lines of the diff for context
        for line in diff[:10]:  # Show only the first few lines of the diff
            print(line)
        print("-" * 80)
        return False
    return True

def check_article_conformance(article_file, template_start, template_end):
    """
    Check if the article conforms with the template's start and end sections.
    """
    with open(article_file, 'r', encoding='utf-8') as f:
        article_lines = f.readlines()

    for line in article_lines:
        #if '--aspect' in line:
        if re.search(r'[^!]--\s*[^>]', line): # Could be improved
            print(f'Illegal syntax "--" in {article_file}')
            return False

    article_start, article_end = extract_relevant_sections(article_lines)

    # Compare start section
    start_conforms = compare_sections(article_file, "start section", article_start, template_start)

    # Compare end section
    end_conforms = compare_sections(article_file, "end section", article_end, template_end)

    return start_conforms and end_conforms

def scan_articles_directory(directory, template_start, template_end):
    """
    Recursively scan the articles directory and check each article's conformance with the template.
    """
    all_conform = True
    n = 0

    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith('.html'):
                article_path = os.path.join(root, file)
                n += 1
                conforms = check_article_conformance(article_path, template_start, template_end)
                if not conforms:
                    all_conform = False

    if all_conform:
        print(f"All articles ({n}) conform with the template.")

def main():
    # Read the template file
    with open(TEMPLATE_FILE, 'r', encoding='utf-8') as f:
        template_lines = f.readlines()

    # Extract relevant sections from the template
    template_start, template_end = extract_relevant_sections(template_lines)

    # Scan the articles directory and check conformance
    scan_articles_directory(ARTICLES_DIR, template_start, template_end)

    os.system("pause")

if __name__ == '__main__':
    main()