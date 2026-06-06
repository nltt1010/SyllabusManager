param(
    [Parameter(Mandatory = $true)]
    [string]$DocumentPath
)

$word = $null
$document = $null

try {
    $resolvedPath = (Resolve-Path -LiteralPath $DocumentPath).Path

    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0

    $document = $word.Documents.Open($resolvedPath, $false, $false)
    $document.Repaginate()

    foreach ($toc in $document.TablesOfContents) {
        $toc.Update() | Out-Null
    }

    $document.Fields.Update() | Out-Null
    $document.Repaginate()
    $document.Save()

    Write-Output 'ok'
    exit 0
} catch {
    Write-Output $_.Exception.Message
    exit 1
} finally {
    if ($document -ne $null) {
        $document.Close($false) | Out-Null
    }

    if ($word -ne $null) {
        $word.Quit() | Out-Null
    }
}
