<?php

class ProjectExportController extends ProjectBaseController
{
    public function beforeRoute(Base $f3)
    {
        parent::beforeRoute($f3);
        if (!$this->currentUser()) {
            $f3->reroute('/login');
        }
    }

    public function export()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'txt');
    }

    public function exportHtml()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'html');
    }

    public function exportEpub()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'ZipArchive extension missing');
            return;
        }
        (new ExportEpubRenderer($this->f3, $this->currentUser()))->render($pid);
    }

    public function exportVector()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'vector');
    }

    public function exportClean()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'clean');
    }

    public function exportSummaries()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'summaries');
    }

    public function exportMarkdown()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        $this->exportFile($pid, 'markdown');
    }

    public function generateExportContent($pid, $format)
    {
        if (!$this->hasProjectAccess((int)$pid)) {
            return null;
        }
        return (new ExportContentService($this->f3, $this->currentUser()))->generate((int)$pid, $format);
    }


    private function exportFile($pid, $format)
    {
        $result = $this->generateExportContent($pid, $format);

        if (!$result) {
            $this->f3->error(404);
            return;
        }

        $content = $result['content'];
        $ext     = $result['ext'];

        if ($format === 'vector') {
            header('Content-Type: application/json');
        } elseif ($format === 'markdown') {
            header('Content-Type: text/plain; charset=utf-8');
        } else {
            header('Content-Type: ' . ($format === 'html' ? 'text/html' : 'text/plain'));
        }

        $filename = "project_{$pid}_" . date('Ymd_His') . "_{$format}." . $ext;
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        exit;
    }


    // sanitizeToXhtml() and generateEpub() -> ExportEpubRenderer

    public function exportOdt()
    {
        $pid = (int) $this->f3->get('PARAMS.id');
        $this->requireProjectAccess($pid);
        if (!class_exists('ZipArchive')) {
            $this->f3->error(500, 'Extension ZipArchive manquante');
            return;
        }
        (new ExportOdtRenderer($this->f3, $this->currentUser()))->render($pid);
    }

    // generateOdt() and ODT helpers -> ExportOdtRenderer
}
