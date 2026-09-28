$root = (Get-Location).Path
$outputFile = Join-Path $root "project_tree.txt"

# Directories to exclude completely
$excludedDirs = @(
    'vendor', 'node_modules', '.git', '.github', '.idea', '.vscode',
    'storage', 'bootstrap\cache', 'stubs', 'public', 'tests'
)

# Relative path patterns to strip out (migrations, views, seeders, assets)
$excludedPathPatterns = @(
    '*\database\migrations*',
    '*\database\seeders*',
    '*\database\factories*',
    '*\resources\views*',
    '*\resources\assets*'
)

# File patterns to exclude
$excludedFiles = @(
    '.env', '.env.*', '*.log', '*.sqlite', 'project_tree.txt',
    '.gitkeep', 'composer.lock', 'package-lock.json', '.editorconfig',
    '.gitattributes', 'README.md', 'phpunit.xml', '*.bat', '*.js'
)

$result = Get-ChildItem -Path $root -Recurse | Where-Object {
    $item = $_
    $relativePath = $item.FullName.Substring($root.Length).TrimStart('\')

    # Exclude root/module folders
    $inExcludedDir = $false
    foreach ($dir in $excludedDirs) {
        if ($relativePath -eq $dir -or $relativePath.StartsWith("$dir\")) {
            $inExcludedDir = $true
            break
        }
    }

    # Exclude nested path patterns
    if (-not $inExcludedDir) {
        foreach ($pattern in $excludedPathPatterns) {
            if ($relativePath -like $pattern) {
                $inExcludedDir = $true
                break
            }
        }
    }

    # Exclude specific files
    $isExcludedFile = $false
    foreach ($filePattern in $excludedFiles) {
        if ($item.Name -like $filePattern) {
            $isExcludedFile = $true
            break
        }
    }

    -not $inExcludedDir -and -not $isExcludedFile
} | ForEach-Object {
    $_.FullName.Substring($root.Length).TrimStart('\')
}

# Save filtered tree to output file in UTF-8
$result | Out-File -FilePath $outputFile -Encoding utf8