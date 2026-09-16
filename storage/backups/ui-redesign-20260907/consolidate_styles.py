from pathlib import Path
import re

root = Path('C:/xampp/htdocs/smartqms')
for relative in ('assets/css/style.css', 'assets/css/admin.css'):
    path = root / relative
    css = path.read_text(encoding='utf-8-sig')
    # All root declarations are flat, one-per-line custom properties. Preserve
    # the cascade's final value, then give those tokens a single owner.
    pattern = re.compile(r'^:root \{\n(.*?)^\}', re.M | re.S)
    blocks = list(pattern.finditer(css))
    values = {}
    for block in blocks:
        for name, value in re.findall(r'^\s*(--[\w-]+|color-scheme):\s*(.*);\s*$', block[1], re.M):
            values[name] = value
    if relative.endswith('/style.css'):
        values.update({'--sq-neutral-60': 'var(--sq-canvas)', '--sq-navy-30': 'var(--sq-government-navy)', '--sq-cta-10': 'var(--sq-action-blue)', '--sq-shadow-card': 'none'})
    merged = ':root {\n' + ''.join(f'  {name}: {value};\n' for name, value in values.items()) + '}\n'
    for block in reversed(blocks):
        css = css[:block.start()] + (merged if block == blocks[0] else '') + css[block.end():]
    # Consolidate the three equivalent shared dark-theme blocks as well.
    if relative.endswith('/style.css'):
        dark = re.compile(r'^html\[data-app-theme="dark"\],\nhtml\[data-admin-theme="dark"\],\nhtml\[data-staff-theme="dark"\] \{\n(.*?)^\}', re.M | re.S)
        blocks = list(dark.finditer(css))
        values = {}
        for block in blocks:
            for name, value in re.findall(r'^\s*(--[\w-]+|color-scheme):\s*(.*);\s*$', block[1], re.M):
                values[name] = value
        values.update({'--sq-navy': 'var(--sq-secondary)', '--sq-warning': '#FFD166', '--sq-warning-soft': '#493915', '--sq-danger': '#FF8A80', '--sq-danger-soft': '#4A252B', '--sq-shadow-card': 'none'})
        merged = 'html[data-app-theme="dark"],\nhtml[data-admin-theme="dark"],\nhtml[data-staff-theme="dark"] {\n' + ''.join(f'  {name}: {value};\n' for name, value in values.items()) + '}\n'
        for block in reversed(blocks):
            css = css[:block.start()] + (merged if block == blocks[0] else '') + css[block.end():]
        css = re.sub(r"@font-face \{\n  font-family: 'IBM Plex Sans';.*?\n\}\n", '', css, flags=re.S)
        css = css.replace('Font: Poppins (headings) + Inter (body)', 'Font: locally hosted Poppins throughout')
        css = css.replace('IBM Carbon production contract', 'Shared component geometry')
        css = css.replace('Strict IBM public intake treatment: neutral tile, blue reserved for action.', 'Public intake: neutral surfaces with blue reserved for actions.')
        css = css.replace('This final layer implements docs/SMARTQMS_COMPLETE_UIUX_PROMPT_ARCHITECTURE.md\n * and intentionally supersedes the earlier experimental Carbon skin.', 'Component bindings for docs/SMARTQMS_COMPLETE_UIUX_PROMPT_ARCHITECTURE.md.\n * Shared light and dark tokens are defined once above.')
        css = css.replace('  font-size: 0;\n  white-space: nowrap;\n}', '  font-size: .875rem;\n  white-space: nowrap;\n}')
        css = css.replace(".staff-table-action {\n  display: inline-grid;\n  width: 44px;\n  height: 44px;\n  flex: 0 0 44px;\n  place-items: center;\n  border: 1px solid var(--sq-primary);\n  border-radius: 0;", ".staff-table-action {\n  display: inline-flex;\n  min-width: 44px;\n  min-height: 44px;\n  padding: 10px 14px;\n  flex: 0 0 auto;\n  align-items: center;\n  justify-content: center;\n  gap: 8px;\n  border: 1px solid var(--sq-link);\n  border-radius: var(--sq-radius-control);")
        css = css.replace('  width: 240px;\n  min-width: 240px;\n  padding-inline: 12px;', '  min-width: 320px;\n  padding-inline: 16px;')
        css = css.replace('.public-journey-page {\n  overflow-x: hidden;', '.public-journey-page {\n  overflow-wrap: break-word;')
        css = css.replace('.public-section { padding-block: clamp(64px, 8vw, 104px); }', '.public-section { padding-block: clamp(40px, 5vw, 72px); }')
        css = css.replace('.public-hero { position: relative; padding-block: clamp(64px, 9vw, 118px); background: linear-gradient(135deg, var(--sq-canvas) 0%, var(--sq-surface-soft) 100%); }', '.public-hero { position: relative; padding-block: clamp(40px, 6vw, 80px); background: var(--sq-canvas); }')
        css = css.replace('.public-hero h1 { max-width: 16ch; margin-bottom: 22px; color: var(--sq-text-strong); font-size: clamp(2.35rem, 5.2vw, 4.65rem); font-weight: 600; letter-spacing: -.055em; line-height: 1.03; text-wrap: balance; }', '.public-hero h1 { max-width: 18ch; margin-bottom: 24px; color: var(--sq-text-strong); font-size: clamp(2.5rem, 4.6vw, 4.25rem); font-weight: 600; letter-spacing: -.035em; line-height: 1.12; text-wrap: balance; }')
        css = css.replace('.public-hero h1 { font-size: clamp(2.2rem, 12vw, 3.35rem); }', '.public-hero h1 { font-size: clamp(2rem, 9.5vw, 3rem); }')
        css = css.replace('.public-navbar-priority .btn { min-height: 40px;', '.public-navbar-priority .btn { min-height: 44px;')
        css = css.replace('.public-ticket-details dt { color: var(--sq-text-muted); font-size: .75rem;', '.public-ticket-details dt { color: var(--sq-text-muted); font-size: .875rem;')
    css = re.sub(r'\n{4,}', '\n\n\n', css)
    path.write_text(css, encoding='utf-8')
    print(f'{relative}: consolidated {len(values)} tokens')
