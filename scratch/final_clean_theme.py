import os
import re
import subprocess

def clean_file(filepath):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    orig = content

    # 1. Clean stacked classes
    content = re.sub(r'\bbg-white\s+bg-\[#112433\]\b', 'bg-[#112433]', content)
    content = re.sub(r'\bbg-neutral-900\s+bg-\[#112433\]\b', 'bg-[#112433]', content)
    content = re.sub(r'\bbg-white\s+bg-\[#0c1924\]\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bborder-neutral-200\s+border-\[#1e3a50\]\b', 'border-[#1e3a50]', content)
    content = re.sub(r'\bborder-neutral-300\s+border-\[#1e3a50\]\b', 'border-[#1e3a50]', content)
    content = re.sub(r'\btext-neutral-900\s+text-white\b', 'text-white', content)
    content = re.sub(r'\btext-white\s+text-white\b', 'text-white', content)

    # 2. Date, time, number inputs
    content = re.sub(
        r'(<input\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#08131c] border-[#1e3a50] text-slate-100\2',
        content
    )
    content = re.sub(
        r'(<input\b[^>]*class=[\"\'][^\"\']*)\bbg-neutral-900\b([^\"\']*[\"\'])',
        r'\1bg-[#08131c] border-[#1e3a50] text-slate-100\2',
        content
    )

    # 3. Clean JS classList dark:bg-white
    content = re.sub(r"['\"]dark:bg-white['\"]", "'dark:bg-[#112433]'", content)

    # 4. Links and buttons with standalone bg-white
    content = re.sub(
        r'(<(?:a|button)\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#112433] text-slate-200 border-[#234257]\2',
        content
    )

    # 5. Icon boxes with standalone bg-white
    content = re.sub(
        r'(<div\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#0c1924]\2',
        content
    )
    content = re.sub(
        r'(<span\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#112433]\2',
        content
    )

    if content != orig:
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        res = subprocess.run(['php', '-l', filepath], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if res.returncode != 0:
            print(f"Error in {filepath}! Reverting...")
            with open(filepath, 'w', encoding='utf-8') as f:
                f.write(orig)
            return False
        return True
    return False

skip = ['clients_directory.php', 'index.php', 'rep_dashboard.php', 'logistics_reports.php', 'system_settings.php', 'profile.php']

modified_files = []
for root, dirs, files in os.walk('modules'):
    for f in files:
        if f.endswith('.php'):
            path = os.path.join(root, f)
            if any(path.endswith(s) for s in skip):
                continue
            if clean_file(path):
                modified_files.append(path)

print(f"Final clean updated {len(modified_files)} files!")
