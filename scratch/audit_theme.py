import os
import re

directories = ['modules', 'includes', 'auth', 'database', 'config']
root_files = ['index.php', 'rep_dashboard.php']

all_files = []
for rf in root_files:
    if os.path.exists(rf):
        all_files.append(rf)

for d in directories:
    if os.path.isdir(d):
        for root, dirs, files in os.walk(d):
            for f in files:
                if f.endswith('.php'):
                    all_files.append(os.path.join(root, f))

patterns = {
    'bg-white': re.compile(r'\bbg-white\b'),
    'bg-neutral-900': re.compile(r'\bbg-neutral-900\b'),
    'bg-neutral-950': re.compile(r'\bbg-neutral-950\b'),
    'bg-gray-50': re.compile(r'\bbg-gray-50\b'),
    'bg-slate-50': re.compile(r'\bbg-slate-50\b'),
    'text-gray-900': re.compile(r'\btext-gray-900\b'),
    'text-neutral-900': re.compile(r'\btext-neutral-900\b'),
    'dark:bg-neutral-900': re.compile(r'\bdark:bg-neutral-900\b'),
    'dark:border-neutral-800': re.compile(r'\bdark:border-neutral-800\b'),
}

counts = {k: 0 for k in patterns}
file_matches = {k: [] for k in patterns}

for path in all_files:
    with open(path, 'r', encoding='utf-8', errors='ignore') as f:
        content = f.read()
    for name, pat in patterns.items():
        m = pat.findall(content)
        if m:
            counts[name] += len(m)
            file_matches[name].append((path, len(m)))

print("=== THEME AUDIT COUNTS ===")
for k, v in counts.items():
    print(f"{k}: {v} occurrences across {len(file_matches[k])} files")
