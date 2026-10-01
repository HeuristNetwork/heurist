<?php
/** Persistent Zotero checkpoints and retry queues. No API keys are stored here. */
class ZoteroSyncStatus {
    private $path;
    private $lock;
    private $data;
    private $library;
    public $state;

    public function __construct($directory, $library, $legacy = []) {
        $this->path = rtrim($directory, '/').'/zotero_sync_status.json';
        $this->library = $library;
        $this->lock = fopen($this->path.'.lock', 'c');
        if (!$this->lock || !flock($this->lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Another Zotero synchronisation is running, or the settings directory is not writable.');
        }
        $this->data = ['schema_version' => 1, 'libraries' => []];
        if (file_exists($this->path)) {
            $raw = file_get_contents($this->path);
            $decoded = $raw === false ? null : json_decode($raw, true);
            if (!is_array($decoded) || !isset($decoded['libraries']) || !is_array($decoded['libraries'])) {
                throw new RuntimeException('Cannot read settings/zotero_sync_status.json. Existing status has been retained; repair the file before syncing.');
            }
            $this->data = $decoded;
        }
        $this->state = $this->data['libraries'][$library] ?? [
            'last_version' => max(0, intval($legacy['id'] ?? 0)),
            'last_sync' => $legacy['date'] ?? null,
            'failed_records' => []
        ];
        if (!is_array($this->state['failed_records'] ?? null)) {
            throw new RuntimeException('Invalid failed_records in settings/zotero_sync_status.json.');
        }
    }

    public function fail($key, $title, $record, $reason, $category = 'error') {
        $old = $this->state['failed_records'][$key] ?? [];
        $this->state['failed_records'][$key] = [
            'zotero_key' => (string)$key, 'title' => (string)$title,
            'heurist_record_id' => intval($record), 'category' => $category,
            'error' => preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', (string)$reason),
            'first_failed' => $old['first_failed'] ?? gmdate('c'), 'last_attempt' => gmdate('c')
        ];
    }

    public function complete($key) { unset($this->state['failed_records'][$key]); }

    public function save() {
        $this->data['libraries'][$this->library] = $this->state;
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        $temp = tempnam(dirname($this->path), '.zotero-status-');
        if ($json === false || $temp === false) { throw new RuntimeException('Cannot encode or create Zotero sync status.'); }
        if (file_put_contents($temp, $json) !== strlen($json) || !rename($temp, $this->path)) {
            @unlink($temp);
            throw new RuntimeException('Cannot save settings/zotero_sync_status.json. Check settings directory permissions.');
        }
        if (file_get_contents($this->path) !== $json) {
            throw new RuntimeException('Zotero status read-back verification failed.');
        }
    }

    public function release() {
        if (is_resource($this->lock)) { flock($this->lock, LOCK_UN); fclose($this->lock); }
        $this->lock = null;
    }
    public function __destruct() { $this->release(); }
}

/** Shared by the setup screen and completion report. */
function zoteroFailedRecordsReport($records) {
    global $syncingStep;
    if (!$records) { return '<p>No records awaiting retry.</p>'; }
    $html = '';
    $counts = [];
    $unsupported = "Zotero type\tZotero key\tTitle\n";
    $actionable = [];
    foreach ($records as $key => $entry) {
        if (!empty($entry['zotero_type']) && ($entry['category'] ?? '') === 'mapping') {
            $type = $entry['zotero_type'];
            $counts[$type] = ($counts[$type] ?? 0) + 1;
            $unsupported .= implode("\t", array_map(function($value) { return preg_replace('/[\t\r\n]+/', ' ', (string)$value); },
                [$type, $key, $entry['title'] ?? '']))."\n";
        } else { $actionable[$key] = $entry; }
    }
    if ($counts) {
        ksort($counts);
        $html .= '<p><strong>Unsupported Zotero records: '.array_sum($counts).'</strong></p><table>';
        foreach ($counts as $type => $count) { $html .= '<tr><td>'.htmlspecialchars($type).'</td><td>'.$count.'</td></tr>'; }
        $html .= '</table><p><a download="Zotero_unsupported_records.txt" href="data:text/plain;charset=utf-8,'.rawurlencode($unsupported).'">Download unsupported records as a tab-separated text file</a></p>';
    }
    if (!$actionable) { return $html; }
    $html .= '<details class="zotero-failures"><summary><strong>Records requiring attention: '.count($actionable).'</strong> — show persistent list</summary>'
        .'<table><thead><tr><th>Zotero key</th><th>Heurist H-ID</th><th>Title</th><th>Error / reason</th><th>Last attempt</th>'
        .(!$syncingStep ? '<th>Action</th>' : '').'</tr></thead><tbody>';
    foreach ($actionable as $entry) {
        $html .= '<tr>';
        foreach (['zotero_key','heurist_record_id','title','error','last_attempt'] as $field) {
            $html .= '<td>'.htmlspecialchars((string)($entry[$field] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</td>';
        }
        if (!$syncingStep) {
            $onclick = 'markZoteroManuallyCorrected('.json_encode($entry['zotero_key']).')';
            $html .= '<td><button type="button" onclick="'.htmlspecialchars($onclick, ENT_QUOTES).'">Mark manually corrected</button></td>';
        }
        $html .= '</tr>';
    }
    return $html.'</tbody></table></details>';
}
