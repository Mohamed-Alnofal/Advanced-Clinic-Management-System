<?php

use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Relations\Relation;

$outputPath = base_path('models_summary.md');
$models = [];

// Locate all model files in app/ and Modules/
$directories = [
    base_path('app/Models'),
    ...File::glob(base_path('Modules/*/Models')),
    ...File::glob(base_path('Modules/*/app/Models')),
];

$files = [];
foreach ($directories as $dir) {
    if (File::isDirectory($dir)) {
        $files = array_merge($files, File::allFiles($dir));
    }
}

$markdown = "# Domain Models & Relationships Summary\n\n";

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;

    // Resolve class namespace from file path
    $relativePath = str_replace([base_path() . '/', '.php', '/app/'], ['', '', '/'], $file->getPathname());
    $className = str_replace('/', '\\', ucfirst($relativePath));

    if (!class_exists($className)) {
        // Fallback namespace resolution for modules
        $content = File::get($file->getPathname());
        if (preg_match('/namespace\s+(.+?);/', $content, $matches)) {
            $className = $matches[1] . '\\' . $file->getBasename('.php');
        }
    }

    if (!class_exists($className)) continue;

    try {
        $reflection = new ReflectionClass($className);
        if ($reflection->isAbstract() || !$reflection->isSubclassOf('Illuminate\Database\Eloquent\Model')) {
            continue;
        }

        $model = new $className();
        $shortName = $reflection->getShortName();
        $table = $model->getTable();
        $fillable = $model->getFillable();
        $casts = $model->getCasts();

        $markdown .= "## Model: {$shortName}\n";
        $markdown .= "- **Class:** `{$className}`\n";
        $markdown .= "- **Table:** `{$table}`\n";
        $markdown .= "- **Fillable Attributes:** " . implode(', ', $fillable) . "\n";
        
        if (!empty($casts)) {
            $markdown .= "- **Casts:** " . json_encode($casts) . "\n";
        }

        // Scan methods for Eloquent relationships
        $relationships = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class !== $className || $method->getNumberOfParameters() > 0) continue;

            try {
                $return = $method->invoke($model);
                if ($return instanceof Relation) {
                    $relType = (new ReflectionClass($return))->getShortName();
                    $relatedModel = (new ReflectionClass($return->getRelated()))->getShortName();
                    $relationships[] = "  - `{$method->getName()}()`: **{$relType}** -> `{$relatedModel}`";
                }
            } catch (\Throwable $e) {
                // Ignore non-relationship public methods
            }
        }

        if (!empty($relationships)) {
            $markdown .= "- **Relationships:**\n" . implode("\n", $relationships) . "\n";
        }

        $markdown .= "\n---\n\n";
    } catch (\Throwable $e) {
        continue;
    }
}

File::put($outputPath, $markdown);
echo "Successfully exported model summary to models_summary.md!\n";