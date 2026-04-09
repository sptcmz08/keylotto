param(
    [Parameter(Mandatory = $true)]
    [string]$ServerHost,

    [Parameter(Mandatory = $true)]
    [string]$ServerUser,

    [int]$ServerPort = 22,
    [string]$RemoteBindAddress = "127.0.0.1",
    [int]$RemotePort = 5001,
    [string]$LocalHost = "127.0.0.1",
    [int]$LocalPort = 5001
)

$ssh = Get-Command ssh -ErrorAction Stop

$forwardSpec = "${RemoteBindAddress}:${RemotePort}:${LocalHost}:${LocalPort}"

Write-Host "Starting reverse SSH tunnel..." -ForegroundColor Cyan
Write-Host "Server: $ServerUser@$ServerHost:$ServerPort"
Write-Host "Remote bind: $RemoteBindAddress`:$RemotePort"
Write-Host "Local worker: $LocalHost`:$LocalPort"
Write-Host ""
Write-Host "Keep this window open while LINE automation is in use." -ForegroundColor Yellow
Write-Host ""

& $ssh.Source `
    -NT `
    -o ExitOnForwardFailure=yes `
    -o ServerAliveInterval=30 `
    -o ServerAliveCountMax=3 `
    -p $ServerPort `
    -R $forwardSpec `
    "$ServerUser@$ServerHost"
