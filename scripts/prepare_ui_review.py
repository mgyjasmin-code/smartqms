"""Refresh the fictional UI review assets. Keep the captured baseline intact."""
from pathlib import Path
import argparse
import shutil
import subprocess

parser = argparse.ArgumentParser()
parser.add_argument('--php', default='php')
args = parser.parse_args()
root = Path(__file__).resolve().parent.parent
preview = root / 'docs/ui-ux/redesign/preview'
subprocess.run([args.php, str(root / 'scripts/render_ui_review.php'), 'after'], cwd=root, check=True)
shutil.copytree(root / 'assets/css', preview / 'after/assets/css', dirs_exist_ok=True)
for name in ['bootstrap', 'fonts', 'lucide', 'chart.js']:
    shutil.copytree(root / 'assets/vendor' / name, preview / 'after/assets/vendor' / name, dirs_exist_ok=True)
shutil.copytree(root / 'assets/images/brand', preview / 'after/assets/images/brand', dirs_exist_ok=True)
for name in ['theme', 'language', 'main', 'shell', 'admin']:
    shutil.copy2(root / f'assets/js/{name}.js', preview / f'after/assets/js/{name}.js')
if (preview.parent / 'screenshots').exists():
    shutil.copytree(preview.parent / 'screenshots', preview / 'screenshots', dirs_exist_ok=True)

# Baseline layout stays unchanged; use its matching scripts for chart rendering
# and accessible navigation. Operational scripts and API configuration stay absent.
extra = ''.join(f'<script src="assets/js/{name}.js"></script>' for name in ['language', 'main', 'shell', 'admin'])
for file in (preview / 'before').glob('*.html'):
    html = file.read_text(encoding='utf-8')
    if 'src="assets/js/admin.js"' not in html:
        html = html.replace('<script src="../review.js">', extra + '<script src="../review.js">')
    import re
    html = re.sub(r'(<meta name="csrf-token" content=")[^"]*', r'\1review-only', html)
    file.write_text(html, encoding='utf-8')
print('Review ready. Serve docs/ui-ux/redesign/preview on loopback port 8766.')
