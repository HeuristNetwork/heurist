<?php
/**
* IiifAnnotationJson.php - Parser/composer for IIIF/Web Annotation JSON.
*
* This helper intentionally contains no database persistence logic. DbAnnotations
* loads/saves recDetails and resolves Heurist terms; this class only parses incoming
* annotation JSON and composes outgoing Web Annotation JSON from plain values.
*/
namespace hserv\iiif;

/**
 * Parser/composer for IIIF Presentation v3 / Web Annotation JSON used by Mirador
 * and Heurist annotation output.
 */
class IiifAnnotationJson
{
    private ?string $lastError = null;

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    private function setError(string $message): void
    {
        $this->lastError = $message;
    }

    /**
     * Parse an incoming annotation payload from Mirador/import into a normalized array.
     * Returns null and sets lastError on invalid input.
     */
    public function parseIncomingAnnotation(array $fields): ?array
    {
        $this->lastError = null;

        $anno = $fields['annotation'] ?? null;
        if(!$anno){
            $this->setError('Annotation data is not defined');
            return null;
        }

        $state = $fields['state'] ?? (($fields['source'] ?? null) === 'mirador' ? 'mirador' : 'imported');

        if(is_array($anno) && isset($anno['data'])){
            $decoded = json_decode($anno['data'], true);
            if(!is_array($decoded)){
                $this->setError('Annotation JSON is invalid: '.json_last_error_msg());
                return null;
            }
            $id = $anno['uuid'] ?? ($decoded['id'] ?? ($decoded['@id'] ?? null));
            $parsed = $this->parseWebAnnotationArray($decoded, $id, $anno['canvas'] ?? null);
            $parsed['json'] = json_encode($decoded, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        if(!is_array($anno)){
            $this->setError('Annotation data is not an array');
            return null;
        }

        if($this->isOpenAnnotation($anno)){
            $webAnno = $this->convertOpenAnnotationToWebAnnotation($anno, $fields['canvasOriginalId'] ?? null);
            $parsed = $this->parseWebAnnotationArray($webAnno, $webAnno['id'] ?? null, $fields['canvasOriginalId'] ?? null);
            $parsed['json'] = json_encode($webAnno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        if(($anno['type'] ?? null) === 'Annotation'){
            $parsed = $this->parseWebAnnotationArray($anno, $anno['id'] ?? null, $fields['canvasOriginalId'] ?? null);
            $parsed['json'] = json_encode($anno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            $parsed['state'] = $state;
            return $parsed;
        }

        $this->setError('Unsupported annotation format');
        return null;
    }

    private function parseLanguageFromBody($body): ?string
    {
        if(!is_array($body)){
            return null;
        }

        if(isset($body['lang'])){
            return is_array($body['lang']) ? reset($body['lang']) : $body['lang'];
        }

        if(isset($body['language'])){
            return is_array($body['language']) ? reset($body['language']) : $body['language'];
        }

        if(isset($body['languages'])){
            return is_array($body['languages']) ? reset($body['languages']) : $body['languages'];
        }

        return null;
    }

    public function parseWebAnnotationArray(array $anno, $fallbackId=null, $fallbackCanvas=null): array
    {
        $bodyText = '';
        $language = null;
        $body = $anno['body'] ?? null;
        $bodies = is_array($body) && array_keys($body)===range(0, count($body)-1) ? $body : array($body);
        foreach($bodies as $b){
            if(is_array($b)){
                if((($b['type'] ?? null) === 'TextualBody' || array_key_exists('value', $b)) && ($b['value'] ?? null) !== null){
                    $bodyText = $b['value'];
                    $lang = $this->parseLanguageFromBody($b);
                    if($lang){
                        $language = $lang;
                    }
                    break;
                }
            }elseif(is_string($b) && $b!==''){
                $bodyText = $b;
                break;
            }
        }

        $motivation = $anno['motivation'] ?? null;
        if(is_array($motivation)){
            $motivation = reset($motivation);
        }
        $motivation = $this->stripPrefix((string)$motivation);

        $target = $anno['target'] ?? null;
        $canvas = $fallbackCanvas;
        $selectorType = null;
        $selectorValue = null;

        if(is_string($target)){
            $parts = explode('#', $target, 2);
            $canvas = $parts[0];
            if(isset($parts[1]) && $parts[1] !== ''){
                $selectorType = 'FragmentSelector';
                $selectorValue = $parts[1];
            }
        }elseif(is_array($target)){
            if(array_keys($target)===range(0, count($target)-1)){
                $target = reset($target);
            }
            $canvas = $target['source'] ?? ($target['id'] ?? $canvas);
            $selector = $this->choosePrimarySelector($target['selector'] ?? null);
            if(is_array($selector)){
                $selectorType = $this->stripPrefix((string)($selector['type'] ?? ($selector['@type'] ?? '')));
                $selectorValue = $selector['value'] ?? null;
            }
        }

        $id = $anno['id'] ?? ($anno['@id'] ?? $fallbackId);
        if(!$id){
            $id = 'heurist-import-'.md5(($canvas ?: '').json_encode($anno));
        }

        return array(
            'id' => $id,
            'body_text' => $bodyText,
            'motivation' => $motivation ?: 'commenting',
            'language' => $language,
            'canvas' => $canvas,
            'selector_type' => $selectorType,
            'selector_value' => $selectorValue,
            'json' => json_encode($anno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
        );
    }

    public function choosePrimarySelector($selector)
    {
        if(!is_array($selector)){
            return null;
        }

        if(array_keys($selector)===range(0, count($selector)-1)){
            $fallback = null;
            foreach($selector as $sel){
                if(!is_array($sel)){
                    continue;
                }
                $type = $this->stripPrefix((string)($sel['type'] ?? ($sel['@type'] ?? '')));
                if($type==='SvgSelector'){
                    return $sel;
                }
                if($type==='FragmentSelector' && $fallback===null){
                    $fallback = $sel;
                }elseif($fallback===null){
                    $fallback = $sel;
                }
            }
            return $fallback;
        }

        if(($selector['type'] ?? null)==='Choice' || ($selector['@type'] ?? null)==='oa:Choice'){
            if(is_array($selector['item'] ?? null)){
                $chosen = $this->choosePrimarySelector($selector['item']);
                if($chosen){ return $chosen; }
            }
            if(is_array($selector['items'] ?? null)){
                $chosen = $this->choosePrimarySelector($selector['items']);
                if($chosen){ return $chosen; }
            }
            if(is_array($selector['default'] ?? null)){
                return $selector['default'];
            }
        }

        return $selector;
    }

    public function stripPrefix($value): string
    {
        $pos = strrpos((string)$value, ':');
        return $pos!==false ? substr((string)$value, $pos+1) : (string)$value;
    }

    private function isOpenAnnotation(array $anno): bool
    {
       return ($anno['@type'] ?? null)==='oa:Annotation' || ($anno['type'] ?? null)==='oa:Annotation';
    }

    public function convertOpenAnnotationToWebAnnotation(array $anno, $fallbackCanvas=null): array
    {
        $id = $anno['@id'] ?? ($anno['id'] ?? null);
        $motivation = $anno['motivation'] ?? null;
        if(is_array($motivation)){
            $motivation = reset($motivation);
        }
        $motivation = $this->stripPrefix((string)$motivation) ?: 'commenting';

        $bodyText = '';
        if(is_array($anno['resource'] ?? null)){
            $res = reset($anno['resource']);
            $bodyText = $res['chars'] ?? ($res['full_text'] ?? '');
        }

        $canvas = $fallbackCanvas;
        $selectors = array();
        if(is_array($anno['on'] ?? null)){
            $target = reset($anno['on']);
            $canvas = $target['full'] ?? $canvas;
            $sel = $target['selector'] ?? null;
            if(is_array($sel)){
                if(isset($sel['default']['value'])){
                    $selectors[] = array('type'=>'FragmentSelector', 'value'=>$sel['default']['value']);
                }
                if(isset($sel['item']['value'])){
                    $selectors[] = array('type'=>'SvgSelector', 'value'=>$sel['item']['value']);
                }
            }
        }

        return array(
            'id' => $id ?: 'heurist-import-'.md5(($canvas ?: '').json_encode($anno)),
            'type' => 'Annotation',
            'motivation' => $motivation,
            'body' => array('type'=>'TextualBody', 'value'=>$bodyText, 'format'=>'text/html'),
            'target' => array('source'=>$canvas, 'selector'=>$selectors)
        );
    }

    /**
     * Compose the IIIF/Web Annotation JSON from a plain data array supplied by DbAnnotations.
     */
    public function buildFromAnnotationData(array $data): ?array
    {
        $stateCode = strtolower((string)($data['stateCode'] ?? ''));
        if(in_array($stateCode, array('obsolete', 'removed'), true)){
            return null;
        }

        $raw = $data['rawJson'] ?? null;
        $anno = $raw ? json_decode($raw, true) : null;
        if(!is_array($anno)){
            $anno = $this->createAnnotationJsonFromData($data);
        }

        if(in_array($stateCode, array('heurist', 'modified'), true)){
            $anno = $this->patchAnnotationJsonFromData($anno, $data);
        }

        // Managed manifests/canvases use the Heurist Canvas API URL as annotation target.
        // Overlay output leaves the original Canvas id from DT_URL untouched.
        if(!empty($data['managedCanvasUrl'])){
            $anno = $this->forceTargetSource($anno, $data['managedCanvasUrl']);
        }

        if(empty($anno['type'])){
            $anno['type'] = 'Annotation';
        }

        // Mirador's annotation plugin treats plain UUID ids more reliably than
        // canonical HTTP ids or source ids containing fragments/encoded URLs.
        // Keep the canonical Heurist/source ids in recDetails; expose a stable,
        // recID-derived UUID only in the viewer JSON.
        $anno['id'] = $this->annotationUuidFromData($data);
        
        return $anno;
    }

    /**
     * Return a stable UUID for Mirador's client-side annotation identity.
     *
     * For persisted Heurist annotations, encode rec_ID into the final 48 bits so
     * the id is deterministic and potentially reversible by server-side adapter
     * code if needed. For non-persisted data, fall back to a deterministic UUID
     * generated from the canonical/source annotation identifier.
     */
    private function annotationUuidFromData(array $data): string
    {
        $recID = intval($data['recID'] ?? 0);
        if($recID > 0){
            $hex = substr(str_pad(dechex($recID), 12, '0', STR_PAD_LEFT), -12);
            return '00000000-0000-4000-8000-'.$hex;
        }

        $seed = (string)($data['annotationApiUrl'] ?? ($data['id'] ?? json_encode($data)));
        return $this->uuidFromString($seed);
    }

    /** Generate a deterministic UUID-shaped id from an arbitrary string. */
    private function uuidFromString(string $seed): string
    {
        $hex = sha1($seed);
        return substr($hex, 0, 8).'-'
            .substr($hex, 8, 4).'-'
            .'5'.substr($hex, 13, 3).'-'
            .dechex((hexdec($hex[16]) & 0x3) | 0x8).substr($hex, 17, 3).'-'
            .substr($hex, 20, 12);
    }

    private function createAnnotationJsonFromData(array $data): array
    {
        $id = $data['id'] ?? null;
        if(!$id){
            $id = $data['annotationApiUrl'] ?? ('heurist-annotation-'.intval($data['recID'] ?? 0));
        }

        $anno = array('id'=>$id, 'type'=>'Annotation');
        $anno = $this->patchAnnotationJsonFromData($anno, $data);
        if(!empty($data['canvas']) && empty($anno['target'])){
            $anno['target'] = array('source'=>$data['canvas']);
        }
        return $anno;
    }

    private function patchAnnotationJsonFromData(array $anno, array $data): array
    {
        if(empty($anno['id'])){
            $anno['id'] = $data['id'] ?? ($data['annotationApiUrl'] ?? ('heurist-annotation-'.intval($data['recID'] ?? 0)));
        }
        $anno['type'] = 'Annotation';

        $bodyText = $data['bodyText'] ?? null;
        if($bodyText!==null && $bodyText!==''){
            $anno['body'] = $this->patchTextualBody($anno['body'] ?? null, $bodyText, $data['language2'] ?? null);
        }

        if(!empty($data['motivation'])){
            $anno['motivation'] = $this->stripPrefix($data['motivation']);
        }

        $canvas = $data['canvas'] ?? null;
        if(empty($anno['target'])){
            $target = array();
            if($canvas){
                $target['source'] = $canvas;
            }
            $selector = $this->buildSelectorFromData($data);
            if($selector){
                $target['selector'] = $selector;
            }
            if(!empty($target)){
                $anno['target'] = $target;
            }
        }elseif(is_array($anno['target'])){
            if(array_keys($anno['target'])===range(0, count($anno['target'])-1)){
                // Multiple targets: do not try to patch complex target arrays from simple fields.
                return $anno;
            }
            if(empty($anno['target']['source']) && $canvas){
                $anno['target']['source'] = $canvas;
            }
            if(empty($anno['target']['selector'])){
                $selector = $this->buildSelectorFromData($data);
                if($selector){
                    $anno['target']['selector'] = $selector;
                }
            }
        }elseif(is_string($anno['target']) && strpos($anno['target'], '#')===false){
            $selector = $this->buildSelectorFromData($data);
            if($selector){
                $anno['target'] = array('source'=>$anno['target'], 'selector'=>$selector);
            }
        }

        return $anno;
    }


    /**
     * Ensure a Web Annotation JSON string contains the supplied target selector.
     * Used for Mirador text-only updates where the editor omits selector even
     * though the annotation area was not changed.
     */
    public function forceTargetSelectorJson(string $json, ?string $selectorType, ?string $selectorValue): string
    {
        if($selectorValue===null || $selectorValue===''){
            return $json;
        }

        $anno = json_decode($json, true);
        if(!is_array($anno)){
            return $json;
        }

        $selectorType = $selectorType ? $this->stripPrefix($selectorType) : 'FragmentSelector';
        if($selectorType==='fragment'){
            $selectorType = 'FragmentSelector';
        }elseif($selectorType==='svg'){
            $selectorType = 'SvgSelector';
        }

        $selector = array('type'=>$selectorType, 'value'=>$selectorValue);
        $anno = $this->forceTargetSelector($anno, $selector);
        $encoded = json_encode($anno, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : $json;
    }

    private function forceTargetSelector(array $anno, array $selector): array
    {
        if(empty($anno['target'])){
            $anno['target'] = array('selector'=>$selector);
            return $anno;
        }

        if(is_string($anno['target'])){
            $parts = explode('#', $anno['target'], 2);
            $anno['target'] = array('source'=>$parts[0], 'selector'=>$selector);
            return $anno;
        }

        if(is_array($anno['target'])){
            if(array_keys($anno['target'])===range(0, count($anno['target'])-1)){
                foreach($anno['target'] as $idx=>$target){
                    if(is_array($target)){
                        $anno['target'][$idx]['selector'] = $selector;
                    }elseif(is_string($target)){
                        $parts = explode('#', $target, 2);
                        $anno['target'][$idx] = array('source'=>$parts[0], 'selector'=>$selector);
                    }
                }
                return $anno;
            }
            $anno['target']['selector'] = $selector;
        }

        return $anno;
    }

    private function forceTargetSource(array $anno, string $canvasUrl): array
    {
        if($canvasUrl===''){
            return $anno;
        }

        if(empty($anno['target'])){
            $anno['target'] = array('source'=>$canvasUrl);
            return $anno;
        }

        if(is_string($anno['target'])){
            $parts = explode('#', $anno['target'], 2);
            $anno['target'] = isset($parts[1]) && $parts[1]!==''
                ? $canvasUrl.'#'.$parts[1]
                : $canvasUrl;
            return $anno;
        }

        if(is_array($anno['target'])){
            if(array_keys($anno['target'])===range(0, count($anno['target'])-1)){
                foreach($anno['target'] as $idx=>$target){
                    if(is_array($target)){
                        $anno['target'][$idx]['source'] = $canvasUrl;
                    }elseif(is_string($target)){
                        $parts = explode('#', $target, 2);
                        $anno['target'][$idx] = isset($parts[1]) && $parts[1]!==''
                            ? $canvasUrl.'#'.$parts[1]
                            : $canvasUrl;
                    }
                }
                return $anno;
            }
            $anno['target']['source'] = $canvasUrl;
        }

        return $anno;
    }

    private function patchTextualBody($body, string $bodyText, ?string $lang2)
    {
        $newBody = array('type'=>'TextualBody', 'value'=>$bodyText, 'format'=>'text/html');
        if($lang2){
            $newBody['language'] = strtolower($lang2);
        }

        if(!is_array($body)){
            return $newBody;
        }

        if(array_keys($body)===range(0, count($body)-1)){
            foreach($body as $idx=>$b){
                if(is_array($b) && (($b['type'] ?? null)==='TextualBody' || array_key_exists('value', $b))){
                    $body[$idx]['type'] = 'TextualBody';
                    $body[$idx]['value'] = $bodyText;
                    if(empty($body[$idx]['format'])){
                        $body[$idx]['format'] = 'text/html';
                    }
                    if($lang2){
                        $body[$idx]['language'] = strtolower($lang2);
                    }
                    return $body;
                }
            }
            array_unshift($body, $newBody);
            return $body;
        }

        if(($body['type'] ?? null)==='TextualBody' || array_key_exists('value', $body)){
            $body['type'] = 'TextualBody';
            $body['value'] = $bodyText;
            if(empty($body['format'])){
                $body['format'] = 'text/html';
            }
            if($lang2){
                $body['language'] = strtolower($lang2);
            }
            return $body;
        }

        return $newBody;
    }

    private function buildSelectorFromData(array $data): ?array
    {
        $selectorValue = $data['selectorValue'] ?? null;
        if($selectorValue===null || $selectorValue===''){
            return null;
        }

        $selectorType = $data['selectorType'] ?? null;
        $selectorType = $selectorType ? $this->stripPrefix($selectorType) : 'FragmentSelector';

        return array('type'=>$selectorType, 'value'=>$selectorValue);
    }
}
