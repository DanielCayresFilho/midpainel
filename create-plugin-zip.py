#!/usr/bin/env python3
import os
import zipfile
from pathlib import Path

def should_exclude(path):
    """Check if a path should be excluded from the ZIP"""
    exclude_patterns = [
        'react/node_modules/',
        'react/src/',
        'react/package.json',
        'react/package-lock.json',
        'react/.gitignore',
        'react/tsconfig.json',
        'react/tsconfig.node.json',
        'react/vite.config.ts',
        'react/postcss.config.js',
        'react/tailwind.config.js',
        'react/components.json',
        'react/eslint.config.js',
        'react/public/',
        '.cursor/',
        '__pycache__/',
        '.git/',
    ]

    for pattern in exclude_patterns:
        if pattern in path:
            return True
    return False

def create_plugin_zip():
    source_dir = Path('/home/unix/git/midpainel/painel-campanhas-install-2')
    output_file = Path('/home/unix/git/midpainel/painel-campanhas-AJAX-FIXED.zip')

    # Remove old file if exists
    if output_file.exists():
        output_file.unlink()

    with zipfile.ZipFile(output_file, 'w', zipfile.ZIP_DEFLATED) as zipf:
        for root, dirs, files in os.walk(source_dir):
            # Calculate relative path
            rel_root = os.path.relpath(root, source_dir.parent)

            # Skip excluded directories
            if should_exclude(rel_root):
                continue

            for file in files:
                file_path = os.path.join(root, file)
                arcname = os.path.join(rel_root, file)

                # Skip excluded files
                if should_exclude(arcname):
                    continue

                zipf.write(file_path, arcname)

    # Get file size
    size_mb = output_file.stat().st_size / (1024 * 1024)
    print(f"Created: {output_file}")
    print(f"Size: {size_mb:.2f} MB")

if __name__ == '__main__':
    create_plugin_zip()
