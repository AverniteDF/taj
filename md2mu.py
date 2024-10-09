import os
import sys
import re
import datetime
import pyperclip

# Function to convert markdown to markup
def markdown_to_markup(content):
    content = content.strip()

    # Remove all blank lines
    content = re.sub(r'\r\n', r'\n', content) #, flags=re.MULTILINE) # Need this, is it a problem with Windows Notepad?
    content = re.sub(r'\n\n+', r'\n', content) #, flags=re.MULTILINE)

    # Replace headers
    content = re.sub(r'^\s*#####\s*(.+?)\s*$', r'<h5>\1</h5>', content, flags=re.MULTILINE)
    content = re.sub(r'^\s*####\s*(.+?)\s*$', r'<h4>\1</h4>', content, flags=re.MULTILINE)
    content = re.sub(r'^\s*###\s*(.+?)\s*$', r'<h3>\1</h3>', content, flags=re.MULTILINE)
    content = re.sub(r'^\s*##\s*(.+?)\s*$', r'<h2>\1</h2>', content, flags=re.MULTILINE)
    content = re.sub(r'^\s*#\s*(.+?)\s*$', r'<h1>\1</h1>', content, flags=re.MULTILINE)

    # Replace horizontal lines (---)
    content = re.sub(r'^\s*---\s*$', r'<hr>', content, flags=re.MULTILINE)

    # Add paragraph markup tags
    content = re.sub(r'^([^<].+)', r'<p>\1</p>', content) #, flags=re.MULTILINE)
    content = re.sub(r'\n([^<].+)', r'\n<p>\1</p>', content) #, flags=re.MULTILINE)

    content = content.replace('\n<h', '\n\n<h')

    content = f'    {content}'
    content = content.replace('\n<', '\n    <')

    return content

# Function to generate a unique filename
def generate_unique_filename(base_name, extension, directory='.'):
    date_str = datetime.datetime.now().strftime("%Y-%m-%d")
    serial = 1
    while True:
        filename = f"{base_name}_{date_str}_{serial:04d}.{extension}"
        if not os.path.exists(os.path.join(directory, filename)):
            return filename
        serial += 1

# Main function to handle file or clipboard input
def main():
    if len(sys.argv) > 1:
        # Case where a file is dragged and dropped onto the script
        input_file = sys.argv[1]
        if os.path.isfile(input_file):
            with open(input_file, 'r', encoding='utf-8') as f:
                content = f.read()
    else:
        # Case where no file is provided, use markdown on clipboard
        print("No file provided. Using clipboard content:")
        content = pyperclip.paste()

    content = content.strip()
    print(f"\n{content}")

    # Convert Markdown to Markup
    markup_content = markdown_to_markup(content)

    # Generate the unique output file name
    output_filename = generate_unique_filename('mu', 'txt')

    # Save the converted content to the output file
    with open(output_filename, 'w', encoding='utf-8') as f:
        f.write(markup_content)

    print(f"\nMarkdown converted to Markup and saved to {os.getcwd()}\\{output_filename}\n")
    os.system("pause")

# Entry point
if __name__ == "__main__":
    try:
        import pyperclip
        main()
    except ImportError:
        print("The 'pyperclip' library is required for clipboard support. Install it with 'pip install pyperclip'.")
