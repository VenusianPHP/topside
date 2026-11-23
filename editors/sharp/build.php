<?php

/*
 * Packs this folder as a Cursor / VS Code extension:
 *   php editors/sharp/build.php
 */

$package = json_decode(file_get_contents(__DIR__.'/package.json'), true, flags: JSON_THROW_ON_ERROR);
$target = __DIR__."/{$package['name']}-{$package['version']}.vsix";

$zip = new ZipArchive();
if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Cannot write {$target}\n");
    exit(1);
}

$zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="utf-8"?>'
    .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    .'<Default Extension="json" ContentType="application/json"/>'
    .'<Default Extension="md" ContentType="text/markdown"/>'
    .'<Default Extension="vsixmanifest" ContentType="text/xml"/>'
    .'</Types>');

$zip->addFromString('extension.vsixmanifest', '<?xml version="1.0" encoding="utf-8"?>'
    .'<PackageManifest Version="2.0.0" xmlns="http://schemas.microsoft.com/developer/vsx-schema/2011">'
    ."<Metadata><Identity Language=\"en-US\" Id=\"{$package['name']}\" Version=\"{$package['version']}\" Publisher=\"{$package['publisher']}\"/>"
    ."<DisplayName>{$package['displayName']}</DisplayName><Description xml:space=\"preserve\">{$package['description']}</Description>"
    .'<Categories>Programming Languages</Categories>'
    ."<Properties><Property Id=\"Microsoft.VisualStudio.Code.Engine\" Value=\"{$package['engines']['vscode']}\"/></Properties></Metadata>"
    .'<Installation><InstallationTarget Id="Microsoft.VisualStudio.Code"/></Installation><Dependencies/>'
    .'<Assets><Asset Type="Microsoft.VisualStudio.Code.Manifest" Path="extension/package.json" Addressable="true"/></Assets>'
    .'</PackageManifest>');

foreach (['package.json', 'language-configuration.json', 'syntaxes/sharp.tmLanguage.json'] as $file) {
    $zip->addFile(__DIR__."/{$file}", "extension/{$file}");
}
$zip->close();

echo $target, "\n";
