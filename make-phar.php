<?php

declare(strict_types=1);

/**
 * Builds HallOfCosmic.phar from the current working tree.
 * Usage: php -dphar.readonly=0 make-phar.php [--out=DIR] [--set-version=X.Y.Z]
 */
function main(): Generator {
    $start = microtime(true);

    $opts = getopt("", ["out:", "set-version:"]);
    $basePath = getcwd();
    $targetPath = $opts["out"] ?? $basePath;

    if (!is_string($basePath) || !is_string($targetPath)) {
        yield "Invalid directory";
        return;
    }

    $basePath .= DIRECTORY_SEPARATOR;
    $targetPath .= DIRECTORY_SEPARATOR;

    if (isset($opts["set-version"]) && is_string($opts["set-version"])) {
        setPluginVersion($basePath, $opts["set-version"]);
    }

    $pluginYml = readPluginYml($basePath);
    $pharName = $pluginYml["name"] . ".phar";
    $pharPath = $targetPath . $pharName;

    if (file_exists($pharPath)) {
        yield "Phar file already exists, overwriting...";
        try {
            Phar::unlinkArchive($pharPath);
        } catch (PharException) {
            unlink($pharPath);
        }
    }

    yield "Adding files...";

    $exclusions = [
        ".git", ".github", ".idea", ".gitignore", ".gitattributes",
        "composer.json", "composer.lock", "make-phar.php", "phpstan.neon",
        "var" . DIRECTORY_SEPARATOR, "vendor" . DIRECTORY_SEPARATOR . "phpstan",
        "vendor" . DIRECTORY_SEPARATOR . "axolotl-pm", $pharName
    ];

    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath)) as $path => $file) {
        $relative = str_replace($basePath, "", $path);
        if (!is_string($relative) || !$file->isFile()) {
            continue;
        }

        foreach ($exclusions as $exclusion) {
            if (str_contains($relative, $exclusion)) {
                continue 2;
            }
        }

        yield "Adding $relative";
        $files[$relative] = $path;
    }

    yield "Compressing...";

    $phar = new Phar($pharPath);
    $phar->startBuffering();
    $phar->setSignatureAlgorithm(Phar::SHA1);
    $phar->setMetadata($pluginYml);

    $count = count($phar->buildFromIterator(new ArrayIterator($files)));
    $phar->compressFiles(Phar::GZ);
    $phar->stopBuffering();

    yield "Added $count files";
    yield "------------------------------------------------";
    yield "BUILD SUCCESS";
    yield "------------------------------------------------";
    yield "Done in " . round(microtime(true) - $start, 1) . "s";
}

/**
 * @return array<string, string>
 */
function readPluginYml(string $basePath): array {
    $contents = file_get_contents($basePath . "plugin.yml");
    if ($contents === false) {
        throw new RuntimeException("Could not read plugin.yml");
    }

    $data = [];
    foreach (explode("\n", $contents) as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#") || !str_contains($line, ":")) {
            continue;
        }

        [$key, $value] = explode(":", $line, 2);
        $data[trim($key)] = trim($value, " \t\n\r\0\x0B\"");
    }

    if (!isset($data["name"], $data["version"])) {
        throw new RuntimeException("plugin.yml is missing 'name' or 'version'");
    }

    return $data;
}

function setPluginVersion(string $basePath, string $version): void {
    $path = $basePath . "plugin.yml";
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Could not read plugin.yml");
    }

    $updated = preg_replace('/^version:\s*.+$/m', "version: \"$version\"", $contents, 1);
    if ($updated === null) {
        throw new RuntimeException("Failed to update plugin.yml version");
    }

    file_put_contents($path, $updated);
}

foreach (main() as $line) {
    echo "[INFO] $line" . PHP_EOL;
}
