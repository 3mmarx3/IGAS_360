import os
import re
import subprocess

def deep_clean_content(content):
    # 1. Clean JS classList toggles that reference bg-white or bg-neutral-900
    content = re.sub(r"['\"]bg-white['\"]", "'bg-[#112433]'", content)
    content = re.sub(r"['\"]bg-neutral-900['\"]", "'bg-[#112433]'", content)
    content = re.sub(r"['\"]border-neutral-900['\"]", "'border-[#234257]'", content)
    content = re.sub(r"['\"]text-neutral-900['\"]", "'text-white'", content)

    # 2. Inverted / high-contrast icon badges
    content = re.sub(
        r'bg-neutral-900\s+(?:dark:bg-white\s+)?text-white\s+(?:text-white\s+)?flex\s+items-center\s+justify-center',
        'bg-[#102230] border border-cyan-500/40 text-cyan-400 flex items-center justify-center',
        content
    )

    # 3. Progress bars / meter fills
    content = re.sub(
        r'\bbg-neutral-900\s+dark:bg-neutral-100\b',
        'bg-cyan-500',
        content
    )
    content = re.sub(
        r'\bmeter-fill\s+h-full\s+bg-neutral-900\b',
        'meter-fill h-full bg-cyan-500',
        content
    )

    # 4. Action buttons & icons using bg-white or bg-neutral-900
    content = re.sub(
        r'(<button\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#112433] text-slate-200 border-[#234257]\2',
        content
    )
    content = re.sub(
        r'(<a\b[^>]*class=[\"\'][^\"\']*)\bbg-white\b([^\"\']*[\"\'])',
        r'\1bg-[#112433] text-slate-200 border-[#234257]\2',
        content
    )
    content = re.sub(
        r'(<button\b[^>]*class=[\"\'][^\"\']*)\bbg-neutral-900\b([^\"\']*[\"\'])',
        r'\1bg-[#112433] text-slate-200 border-[#234257]\2',
        content
    )
    content = re.sub(
        r'(<span\b[^>]*class=[\"\'][^\"\']*)\bbg-neutral-900\b([^\"\']*[\"\'])',
        r'\1bg-[#112433] text-slate-200 border-[#234257]\2',
        content
    )

    # 5. Modal overlays with bg-neutral-900/60
    content = re.sub(
        r'\bbg-neutral-900/60\b',
        'bg-[#060e14]/80 backdrop-blur-sm',
        content
    )

    # 6. Inputs with remaining bg-white or bg-neutral-900
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
    content = re.sub(
        r'(<label\b[^>]*class=[\"\'][^\"\']*)\bhover:bg-white\b([^\"\']*[\"\'])',
        r'\1hover:bg-[#102332]\2',
        content
    )

    # 7. Remaining duplicate or stacked classes like 'bg-[#112433] bg-[#0c1924]' or 'border-[#1e3a50] border-[#1a3346]'
    content = re.sub(r'\bbg-white\s+bg-\[#112433\]\b', 'bg-[#112433]', content)
    content = re.sub(r'\bbg-white\s+bg-\[#0c1924\]\b', 'bg-[#0c1924]', content)
    content = re.sub(r'\bbg-neutral-900\s+bg-\[#112433\]\b', 'bg-[#112433]', content)
    content = re.sub(r'\btext-white\s+text-white\b', 'text-white', content)
    content = re.sub(r'\bbg-neutral-900\s+text-white\b', 'bg-[#112433] text-slate-200', content)

    # 8. Remaining text-neutral-900
    content = re.sub(r'\btext-neutral-900\b', 'text-white', content)

    return content

skip = ['clients_directory.php', 'index.php', 'rep_dashboard.php', 'logistics_reports.php']

count = 0
for root, dirs, files in os.walk('modules'):
    for f in files:
        if f.endswith('.php'):
            path = os.path.join(root, f)
            if any(path.endswith(s) for s in skip):
                continue
            with open(path, 'r', encoding='utf-8') as fp:
                orig = fp.read()
            cleaned = deep_clean_content(orig)
            if cleaned != orig:
                with open(path, 'w', encoding='utf-8') as fp:
                    fp.write(cleaned)
                res = subprocess.run(['php', '-l', path], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                if res.returncode != 0:
                    print(f"Error in {path}! Reverting...")
                    with open(path, 'w', encoding='utf-8') as fp:
                        fp.write(orig)
                else:
                    count += 1

print(f"Deep cleaned {count} files successfully!")
