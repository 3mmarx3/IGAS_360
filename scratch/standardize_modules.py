import os
import re
import subprocess
import sys

def migrate_code(content, filepath):
    orig = content

    # 1. Ensure dark theme is forced in the inline script
    content = re.sub(
        r"const\s+savedTheme\s*=\s*localStorage\.getItem\(['\"]theme['\"]\)\s*\|\|\s*['\"]light['\"];\s*if\s*\(savedTheme\s*===\s*['\"]dark['\"]\)\s*\{\s*document\.documentElement\.classList\.add\(['\"]dark['\"]\);\s*\}\s*else\s*\{\s*document\.documentElement\.classList\.remove\(['\"]dark['\"]\);\s*\}",
        "document.documentElement.classList.add('dark');",
        content
    )

    # 2. Body canvas
    content = re.sub(
        r'bg-grid font-sans text-neutral-900 dark:text-neutral-100[^\"]*',
        'bg-[#071018] font-sans text-slate-100 selection:bg-orange-500/20 selection:text-orange-400',
        content
    )
    content = re.sub(
        r'class=([\"\'])((?:(?!\1).)*?)\bbg-neutral-50/50\s+dark:bg-neutral-900/50\b((?:(?!\1).)*?)\1',
        r'class=\1\2bg-[#071018]\3\1',
        content
    )

    # 3. Telemetry Sub-bar
    content = re.sub(
        r'border-b\s+border-neutral-200\s+dark:border-neutral-800\s+bg-neutral-100/80\s+dark:bg-neutral-900/80',
        'border-b border-[#152938] bg-[#060e14] text-slate-400',
        content
    )
    content = re.sub(
        r'border-b\s+border-neutral-200\s+dark:border-neutral-800\s+bg-neutral-100\s+dark:bg-neutral-900',
        'border-b border-[#152938] bg-[#060e14] text-slate-400',
        content
    )

    # 4. Form inputs, selects, textareas (Task 2)
    # Search for input/select/textarea tags and replace their styling
    def fix_input_tag(match):
        tag_str = match.group(0)
        # Replace background and border
        tag_str = re.sub(r'\bbg-white\s+dark:bg-neutral-900\b', 'bg-[#08131c]', tag_str)
        tag_str = re.sub(r'\bbg-white\b', 'bg-[#08131c]', tag_str)
        tag_str = re.sub(r'\bborder-neutral-300\s+dark:border-neutral-700\b', 'border-[#1e3a50]', tag_str)
        tag_str = re.sub(r'\btext-neutral-900\s+dark:text-neutral-100\b', 'text-slate-100 placeholder-slate-500', tag_str)
        tag_str = re.sub(r'\bfocus:border-neutral-900\s+dark:focus:border-neutral-100\b', 'focus:border-orange-500 focus:ring-1 focus:ring-orange-500/50', tag_str)
        return tag_str

    content = re.sub(r'<(?:input|select|textarea)\b[^>]*>', fix_input_tag, content)

    # 5. Secondary / utility buttons
    content = re.sub(
        r'border\s+border-neutral-300\s+dark:border-neutral-700\s+bg-white\s+dark:bg-neutral-800\s+text-neutral-700\s+dark:text-neutral-300\s+hover:bg-neutral-100\s+dark:hover:bg-neutral-700',
        'border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200',
        content
    )
    content = re.sub(
        r'border\s+border-neutral-300\s+dark:border-neutral-700\s+hover:border-neutral-900\s+dark:hover:border-white\s+rounded-md\s+text-neutral-700\s+dark:text-neutral-300\s+transition-colors',
        'border border-[#234257] bg-[#112433] hover:bg-[#173042] text-slate-200 rounded-md transition-colors',
        content
    )

    # 6. Containers, Cards, and Tables
    content = re.sub(
        r'bg-white\s+dark:bg-neutral-900\s+border\s+border-neutral-200\s+dark:border-neutral-800',
        'bg-[#0c1924] border border-[#1a3346]',
        content
    )
    content = re.sub(
        r'bg-white\s+dark:bg-neutral-950\s+border\s+border-neutral-200\s+dark:border-neutral-800',
        'bg-[#0c1924] border border-[#1a3346]',
        content
    )
    content = re.sub(r'\bbg-white\s+dark:bg-neutral-900\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bbg-white\s+dark:bg-neutral-950\b', 'bg-[#0c1924]', content)

    # 7. Table Thead and Subheaders
    content = re.sub(r'\bbg-neutral-50/60\s+dark:bg-neutral-850\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-100/60\s+dark:bg-neutral-800/50\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-100/60\s+dark:bg-neutral-800/60\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-50\s+dark:bg-neutral-800/40\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-100\s+dark:bg-neutral-800\b', 'bg-[#09151f]', content)

    # 8. Table Rows and Dividers
    content = re.sub(r'\bhover:bg-neutral-50\s+dark:hover:bg-neutral-800/40\b', 'hover:bg-[#102332]', content)
    content = re.sub(r'\bhover:bg-neutral-50\s+dark:hover:bg-neutral-800/50\b', 'hover:bg-[#102332]', content)
    content = re.sub(r'\bhover:bg-neutral-50\b', 'hover:bg-[#102332]', content)
    content = re.sub(r'\bdivide-neutral-200\s+dark:divide-neutral-800\b', 'divide-[#152a3a]', content)
    content = re.sub(r'\bdivide-neutral-100\b', 'divide-[#152a3a]', content)

    # 9. Primary and Utility Borders
    content = re.sub(r'\bborder-neutral-200\s+dark:border-neutral-800\b', 'border-[#1a3346]', content)
    content = re.sub(r'\bborder-neutral-300\s+dark:border-neutral-700\b', 'border-[#1e3a50]', content)
    content = re.sub(r'\bdark:border-neutral-800\b', 'border-[#1a3346]', content)
    content = re.sub(r'\bdark:border-neutral-700\b', 'border-[#1e3a50]', content)

    # 10. Neutral Surfaces and Overlays
    content = re.sub(r'\bbg-neutral-50/(?:40|50)\s+dark:bg-neutral-950/(?:40|50)\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-50/(?:40|50)\s+dark:bg-neutral-900/(?:40|50)\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bdark:bg-neutral-950/40\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bdark:bg-neutral-900/40\b', 'bg-[#09151f]', content)
    content = re.sub(r'\bbg-neutral-950/60\b', 'bg-[#060e14]/80 backdrop-blur-sm', content)
    content = re.sub(r'\bdark:bg-neutral-950\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bbg-neutral-950\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bdark:bg-neutral-900\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bdark:bg-neutral-800\b', 'bg-[#112433]', content)

    # 11. Typography and Hover Text
    content = re.sub(r'\bhover:text-neutral-900\s+dark:hover:text-white\b', 'hover:text-white', content)
    content = re.sub(r'\bhover:text-neutral-900\s+dark:hover:text-neutral-100\b', 'hover:text-white', content)
    content = re.sub(r'\bhover:text-neutral-900\b', 'hover:text-white', content)
    content = re.sub(r'\btext-neutral-900\s+dark:text-neutral-100\b', 'text-white', content)
    content = re.sub(r'\btext-neutral-900\s+dark:text-white\b', 'text-white', content)
    content = re.sub(r'\btext-neutral-900\b', 'text-white', content)
    content = re.sub(r'\bdark:text-white\b', 'text-white', content)
    content = re.sub(r'\bdark:text-neutral-100\b', 'text-white', content)
    content = re.sub(r'\btext-neutral-600\s+dark:text-neutral-400\b', 'text-slate-400', content)
    content = re.sub(r'\btext-neutral-500\s+dark:text-neutral-400\b', 'text-slate-400', content)
    content = re.sub(r'\bdark:text-neutral-400\b', 'text-slate-400', content)
    content = re.sub(r'\btext-neutral-700\s+dark:text-neutral-300\b', 'text-slate-300', content)
    content = re.sub(r'\bdark:text-neutral-300\b', 'text-slate-300', content)
    content = re.sub(r'\btext-neutral-800\s+dark:text-neutral-200\b', 'text-slate-200', content)
    content = re.sub(r'\bdark:text-neutral-200\b', 'text-slate-200', content)

    # 12. KPI cards Left Accent Border in Safety Orange
    content = re.sub(r'\bborder-s-\[3px\]\s+border-s-[a-z0-9\-]+', 'border-s-4 border-s-[#f97316]', content)
    content = re.sub(r'\bborder-l-\[3px\]\s+border-l-[a-z0-9\-]+', 'border-l-4 border-l-[#f97316]', content)

    # Also add border-l-4 border-l-[#f97316] to Bento cards that have p-5 and rounded
    content = re.sub(
        r'(class=[\"\'][^\"\']*bg-\[\#0c1924\][^\"\']*border\s+border-\[\#1a3346\])( p-5 rounded-xs)',
        r'\1 border-l-4 border-l-[#f97316]\2',
        content
    )

    return content

def process_file(filepath):
    # Don't touch clients_directory.php (our golden master), index.php or rep_dashboard.php (already done)
    skip = ['clients_directory.php', 'index.php', 'rep_dashboard.php', 'logistics_reports.php']
    if any(filepath.endswith(s) for s in skip):
        print(f"Skipping master/already-customized file: {filepath}")
        return True

    with open(filepath, 'r', encoding='utf-8') as f:
        original = f.read()

    modified = migrate_code(original, filepath)

    if modified == original:
        return True

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(modified)

    # Run php -l to ensure syntax validity
    res = subprocess.run(['php', '-l', filepath], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    if res.returncode != 0:
        print(f"ERROR: Syntax error in {filepath}! Reverting immediately...")
        print(res.stderr or res.stdout)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(original)
        return False

    print(f"Successfully migrated: {filepath}")
    return True

if __name__ == '__main__':
    target = sys.argv[1] if len(sys.argv) > 1 else 'modules'
    files_to_process = []
    if os.path.isfile(target):
        files_to_process = [target]
    else:
        for root, dirs, files in os.walk(target):
            for f in files:
                if f.endswith('.php'):
                    files_to_process.append(os.path.join(root, f))

    print(f"Processing {len(files_to_process)} files in {target}...")
    errors = 0
    for p in sorted(files_to_process):
        if not process_file(p):
            errors += 1

    print(f"Done. Errors: {errors}")
