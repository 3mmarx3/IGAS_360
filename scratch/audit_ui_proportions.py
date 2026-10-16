import os
import re

workspace_dir = "/Users/3mmarx3/Downloads/untitled folder 2"

php_files = []
for root, dirs, files in os.walk(workspace_dir):
    if "vendor" in root or ".git" in root or "scratch" in root:
        continue
    for f in files:
        if f.endswith(".php"):
            php_files.append(os.path.join(root, f))

print(f"Total PHP files to scan: {len(php_files)}")

# 1. Check for table wrappers missing custom-scrollbar
missing_scrollbar = []
# 2. Check for search inputs
search_inputs = []
# 3. Check for leftover bg-white in module templates
white_bg_elements = []

for file_path in php_files:
    rel_path = os.path.relpath(file_path, workspace_dir)
    with open(file_path, "r", encoding="utf-8", errors="ignore") as fp:
        content = fp.read()
    
    # Check tables with overflow-x-auto missing custom-scrollbar
    for m in re.finditer(r'<div[^>]*class=["\'][^"\']*overflow-x-auto[^"\']*["\']', content):
        class_str = m.group(0)
        if "custom-scrollbar" not in class_str:
            missing_scrollbar.append((rel_path, class_str))

    # Check search/filter inputs
    for m in re.finditer(r'<input[^>]*type=["\'](?:text|search)["\'][^>]*placeholder=["\'][^"\']*(?:search|filter|ابحث)[^"\']*["\'][^>]*>', content, re.IGNORECASE):
        input_tag = m.group(0)
        search_inputs.append((rel_path, input_tag))

    # Check if there are any lingering raw "bg-white" in class attributes
    for m in re.finditer(r'class=["\'][^"\']*\bbg-white\b[^"\']*["\']', content):
        if "badge" not in rel_path and "export" not in rel_path:
            white_bg_elements.append((rel_path, m.group(0)))

print(f"\n--- Tables missing custom-scrollbar: {len(missing_scrollbar)} ---")
for r, c in missing_scrollbar[:15]:
    print(f"  {r}: {c[:80]}")

print(f"\n--- Search inputs found: {len(search_inputs)} ---")
for r, inp in search_inputs[:15]:
    print(f"  {r}: {inp[:90]}")

print(f"\n--- Lingering bg-white elements: {len(white_bg_elements)} ---")
for r, c in white_bg_elements[:15]:
    print(f"  {r}: {c[:80]}")
