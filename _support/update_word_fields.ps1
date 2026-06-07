param(
    [Parameter(Mandatory = $true)]
    [string]$DocumentPath,

    [Parameter(Mandatory = $false)]
    [string]$PdfPath,

    [Parameter(Mandatory = $false)]
    [switch]$ExportPdfOnly
)

$word = $null
$document = $null

try {
    $resolvedPath = (Resolve-Path -LiteralPath $DocumentPath).Path
    $resolvedPdfPath = $null

    if (![string]::IsNullOrWhiteSpace($PdfPath)) {
        $resolvedPdfPath = [System.IO.Path]::GetFullPath($PdfPath)
        $pdfDirectory = [System.IO.Path]::GetDirectoryName($resolvedPdfPath)
        if (![string]::IsNullOrWhiteSpace($pdfDirectory) -and !(Test-Path -LiteralPath $pdfDirectory)) {
            New-Item -ItemType Directory -Path $pdfDirectory -Force | Out-Null
        }
    }

    $readOnly = [bool]$ExportPdfOnly.IsPresent
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0

    $document = $word.Documents.Open($resolvedPath, $false, $readOnly)

    if (-not $ExportPdfOnly) {
        $document.Repaginate()

        foreach ($toc in $document.TablesOfContents) {
            $toc.Update() | Out-Null
        }

        $document.Fields.Update() | Out-Null
        $document.Repaginate()
        $document.Save()
    }

    if ($resolvedPdfPath -ne $null) {
        if (Test-Path -LiteralPath $resolvedPdfPath) {
            Remove-Item -LiteralPath $resolvedPdfPath -Force
        }

        $wdFormatPDF = 17
        $document.Repaginate()
        $document.SaveAs([ref]$resolvedPdfPath, [ref]$wdFormatPDF)
    }

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
