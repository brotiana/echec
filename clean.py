import re
import sys
import os

def remove_comments_js_css(text):
    def replacer(match):
        s = match.group(0)
        if s.startswith('/'):
            # It's a comment
            return " " # Replacer par un espace pour eviter de coller du code
        else:
            # It's a string
            return s
    
    # Regex for:
    # 1. Double quoted string:  "..."
    # 2. Single quoted string:  '...'
    # 3. Template literal:      `...`
    # 4. Multi-line comment:    /* ... */
    # 5. Single-line comment:   // ...
    pattern = re.compile(
        r'//.*?$|/\*.*?\*/|\'(?:\\.|[^\\\'])*\'|"(?:\\.|[^\\"])*"|`(?:\\.|[^\\`])*`',
        re.DOTALL | re.MULTILINE
    )
    return re.sub(pattern, replacer, text)

def remove_comments_html(text):
    # Retrieve content inside <script> and <style> tags to clean them individually? 
    # That might be complex. Simple HTML comment removal first.
    # User asked for "comments on code", likely standard html comments.
    return re.sub(r'<!--[\s\S]*?-->', '', text)

def clean_file(filepath):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        
        ext = os.path.splitext(filepath)[1].lower()
        
        if ext in ['.js', '.css']:
            new_content = remove_comments_js_css(content)
        elif ext in ['.html', '.php']: # Adding php just in case, though mostly concerned with html parts
            # For PHP/HTML mixed files it's tricky. But user said "inscription" and "assets" which are mostly static assets or pure js/css.
            # inscription/index.html is HTML.
            new_content = remove_comments_html(content)
            # If html contains script/style blocks, we might want to clean them too?
            # Let's stick to file-level comments for now unless requested deeper.
            # Actually, `inscription/index.html` might contain JS/CSS. 
            # Let's keep it simple: HTML comments for HTML files.
        else:
            return

        # Basic cleanup of empty lines created by comment removal
        # new_content = re.sub(r'\n\s*\n', '\n', new_content)

        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(new_content)
        print(f"Cleaned {filepath}")
    except Exception as e:
        print(f"Error processing {filepath}: {e}")

if __name__ == "__main__":
    files = sys.argv[1:]
    for f in files:
        clean_file(f)