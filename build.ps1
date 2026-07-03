# build.ps1 — gera os ZIPs de instalação com a estrutura de pastas correta para o Moodle.
#
# REGRA DO MOODLE: o nome da pasta interna do ZIP deve ser o NOME do plugin
# (a parte depois do "_" no frankenstyle), NUNCA o componente completo.
#   local_usersignature                       -> pasta "usersignature"
#   certificatebeautifuldatainfo_usersignature-> pasta "usersignature"
# Usar a pasta errada causa o erro "detectedmisplacedplugin" no Moodle.

$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
$dist = Join-Path $root 'dist'
$staging = Join-Path $root '.staging'

# Limpa saídas anteriores.
Remove-Item -Recurse -Force $dist, $staging -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $dist | Out-Null

# Mapeia: pasta-de-origem  ->  nome-da-pasta-interna-no-zip  ->  nome-do-arquivo-zip
$plugins = @(
    @{ src = 'local_usersignature';                        folder = 'usersignature'; zip = 'local_usersignature.zip' },
    @{ src = 'certificatebeautifuldatainfo_usersignature'; folder = 'usersignature'; zip = 'certificatebeautifuldatainfo_usersignature.zip' }
)

foreach ($p in $plugins) {
    $srcPath = Join-Path $root $p.src
    if (-not (Test-Path $srcPath)) { throw "Origem não encontrada: $srcPath" }

    # Cria staging\<folder> e copia o conteúdo do plugin para dentro dela.
    $stageDir = Join-Path $staging $p.folder
    Remove-Item -Recurse -Force $stageDir -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path $stageDir | Out-Null
    Copy-Item -Recurse -Force (Join-Path $srcPath '*') $stageDir

    # Compacta a PASTA (não o conteúdo), garantindo o nome interno correto.
    # Usa tar.exe (bsdtar) em vez de Compress-Archive: este último grava as
    # entradas com "\" e o Moodle em Linux não consegue ler a estrutura
    # ("Não foi possível detectar o tipo de plugin").
    $zipPath = Join-Path $dist $p.zip
    # Caminho absoluto: evita pegar o tar GNU do Git Bash, que não entende "C:".
    $tarExe = Join-Path $env:SystemRoot 'System32\tar.exe'
    & $tarExe -C $staging -a -cf $zipPath $p.folder
    if ($LASTEXITCODE -ne 0) { throw "tar falhou para $($p.zip)" }

    Write-Host "OK  $($p.zip)  (pasta interna: $($p.folder)/)"
    Remove-Item -Recurse -Force $stageDir
}

Remove-Item -Recurse -Force $staging -ErrorAction SilentlyContinue
Write-Host ""
Write-Host "ZIPs gerados em: $dist"
