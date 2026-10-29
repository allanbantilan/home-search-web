param(
    [ValidateRange(1, 65535)][int]$Port = 57106,
    [ValidatePattern('^[a-z0-9][a-z0-9_-]*$')][string]$Project = 'home-search-local',
    [string]$AdminUser = 'portfolioadmin',
    [string]$AdminEmail = 'admin@example.test',
    [switch]$SeedDemo
)

$ErrorActionPreference = 'Stop'
$env:HOME_SEARCH_PORT = [string]$Port
$compose = Join-Path $PSScriptRoot '.docker-test/compose.yml'
$dockerArgs = @('compose', '-p', $Project, '-f', $compose)

function Invoke-Wp([string[]]$WpArgs) {
    & docker @dockerArgs run --rm -T cli wp @WpArgs
    if ($LASTEXITCODE -ne 0) { throw "WP-CLI failed: $($WpArgs -join ' ')" }
}

& docker @dockerArgs up -d db wordpress
if ($LASTEXITCODE -ne 0) { throw 'Docker Compose could not start WordPress.' }

for ($attempt = 0; $attempt -lt 30; $attempt++) {
    & docker @dockerArgs run --rm -T cli wp db check *> $null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 2
}
if ($LASTEXITCODE -ne 0) { throw 'WordPress could not connect to the database.' }

& docker @dockerArgs run --rm -T cli wp core is-installed *> $null
if ($LASTEXITCODE -ne 0) {
    if ($AdminEmail -notmatch '^[^@\s]+@[^@\s]+\.[^@\s]+$') { throw 'Enter a valid admin email.' }
    $password = [Convert]::ToBase64String([System.Security.Cryptography.RandomNumberGenerator]::GetBytes(24))
    & docker @dockerArgs run --rm -T cli wp core install "--url=http://127.0.0.1:$Port" '--title=Home Search' "--admin_user=$AdminUser" "--admin_email=$AdminEmail" "--admin_password=$password" --skip-email --quiet
    if ($LASTEXITCODE -ne 0) { throw 'WordPress installation failed.' }
    Write-Host "New local admin: $AdminUser / $password"
}

Invoke-Wp @('plugin', 'install', 'advanced-custom-fields', '--activate')
Invoke-Wp @('plugin', 'activate', 'estate-search')
Invoke-Wp @('theme', 'activate', 'property-search')
Invoke-Wp @('rewrite', 'structure', '/%postname%/')
Invoke-Wp @('option', 'update', 'blogname', 'Home Search')
Invoke-Wp @('option', 'update', 'home', "http://127.0.0.1:$Port")
Invoke-Wp @('option', 'update', 'siteurl', "http://127.0.0.1:$Port")

if ($SeedDemo) {
    Invoke-Wp @('eval-file', '/home-search-test/setup.php')
    $photos = @(
        @{ Slug = 'makati-garden-residence'; File = '/var/www/html/wp-content/themes/property-search/assets/images/hero.webp' },
        @{ Slug = 'tagaytay-hillside-retreat'; File = '/home-search-test/images/tagaytay-hillside.webp' },
        @{ Slug = 'cebu-city-modern-loft'; File = '/home-search-test/images/cebu-city-loft.webp' }
    )
    foreach ($photo in $photos) {
        $propertyId = & docker @dockerArgs run --rm -T cli wp post list --post_type=property "--name=$($photo.Slug)" --field=ID
        if ($LASTEXITCODE -ne 0 -or !$propertyId) { throw "Sample property $($photo.Slug) was not created." }
        $thumbnailId = & docker @dockerArgs run --rm -T cli wp post meta get $propertyId _thumbnail_id
        if (!$thumbnailId) { Invoke-Wp @('media', 'import', $photo.File, "--post_id=$propertyId", '--featured_image') }
    }
}

Write-Host "Home Search is ready at http://127.0.0.1:$Port/"
