<?php

declare(strict_types=1);

namespace Dof\Framework\Doc\UI;

use Throwable;
use Dof\Framework\ConfigManager;
use Dof\Framework\Kernel;

class GitBook
{
    const SUPPORT_LANG = [
        'zh-CN' => true,
    ];
    const README  = 'README.md';
    const SUMMARY = 'SUMMARY.md';
    const BUILDER = 'build.sh';
    const DOC_API = 'doc.api';
    const DOC_WRAPIN = 'doc.wrapin';
    const DOC_MODEL  = 'doc.model';
    const DOC_ERROR  = 'errors.md';
    const BOOK_JSON = 'book.json';
    const VER_INDEX = 'index.html';
    const WRAPIN_OUTPUT = '_wrapin';
    const MODEL_OUTPUT  = '_model';

    /** @var string: UI language */
    private $language = 'zh-CN';

    /** @var array: API templates data */
    private $apiData = [];

    /** @var array: Wrapin templates data */
    private $wrapinData = [];

    /** @var array: Data model templates data */
    private $modelData = [];

    /** @var array: Errors templates data */
    private $errorData = [];

    /** @var string: Templates directory */
    private $templates;

    /** @var string: Current template path */
    private $template;

    /** @var string: Output directory */
    private $output;

    /** @var string: SUMMARY.md path */
    private $summary;

    /** @var string: README.md path */
    private $readme;

    /** @var string: book.json path */
    private $bookjson;

    /** @var string: Build all-versions-in-one site */
    private $builder;

    /** @var string: API Doc template path */
    private $docApi;

    /** @var string: Wrapin Doc template path */
    private $docWrapin;

    /** @var string: Data Model Doc template path */
    private $docModel;

    /** @var string: Errors Doc template path */
    private $docError;

    /** @var string: Doc menus tree */
    private $menuTree = '';

    /** @var int: Doc menus depth */
    private $menuDepth = 0;

    /** @var array: Doc versions selects list */
    private $selects = [];

    /** @var array: Doc versions list */
    private $versions = [];

    public function prepare()
    {
        $template = ospath($this->templates, 'gitbook', 'lang', $this->language);
        $summary = ospath($template, self::SUMMARY);
        if (! is_file($summary)) {
            exception('GitBookSummaryNotFound', compact('summary'));
        }
        $readme = ospath($template, self::README);
        if (! is_file($readme)) {
            exception('GitBookReadmeNotFound', compact('readme'));
        }
        $bookjson = ospath($template, self::BOOK_JSON);
        if (! is_file($bookjson)) {
            exception('GitBookJSONFileNotFound', compact('bookjson'));
        }
        $builder = ospath($template, self::BUILDER);
        if (! is_file($builder)) {
            exception('GitBookSiteBuilderNotFound', compact('builder'));
        }
        $docApi = ospath($template, self::DOC_API);
        if (! is_file($docApi)) {
            exception('ApiDocTemplateNotFound', compact('docApi'));
        }
        $docWrapin = ospath($template, self::DOC_WRAPIN);
        if (! is_file($docWrapin)) {
            exception('WrapinDocTemplateNotFound', compact('docWrapin'));
        }
        $docModel = ospath($template, self::DOC_MODEL);
        if (! is_file($docModel)) {
            exception('DataModelDocTemplateNotFound', compact('docModel'));
        }
        $docError = ospath($template, self::DOC_ERROR);
        if (! is_file($docError)) {
            exception('ErrorDocTemplateNotFound', compact('docError'));
        }
        $verindex = ospath($template, self::VER_INDEX);
        if (! is_file($verindex)) {
            exception('DocVersionSelectIndexNotFound', compact('verindex'));
        }

        $this->template = $template;
        $this->summary  = $summary;
        $this->readme   = $readme;
        $this->bookjson = $bookjson;
        $this->builder  = $builder;
        $this->verindex = $verindex;
        $this->docApi    = $docApi;
        $this->docModel  = $docModel;
        $this->docError  = $docError;
        $this->docWrapin = $docWrapin;
    }

    public function buildModel(bool $standalone)
    {
        if (! $this->modelData) {
            return;
        }

        if ($standalone) {
            $this->prepare();
        }

        $path = ospath($this->output, self::MODEL_OUTPUT);
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $this->selects[] = [
            'value'   => '/'.self::MODEL_OUTPUT,
            'text'    => 'Data Model',
            'version' => self::MODEL_OUTPUT,
        ];

        foreach ($this->modelData as $ns => $model) {
            $key = $this->formatDocNamespace($ns);
            $this->appendMenuTree($key, null, $key);
            $this->render($this->docModel, ospath($path, "{$key}.md"), $this->formatModelDocData($model, $key));
        }

        $readme = ospath($path, self::README);
        if (is_file($readme)) {
            unlink($readme);
        }
        $this->render($this->readme, $readme, ['version' => 'Data Model']);

        $summary = ospath($path, self::SUMMARY);
        if (is_file($summary)) {
            unlink($summary);
        }
        $this->render($this->summary, $summary, ['tree' => $this->menuTree, 'readme' => true]);

        $this->menuTree  = '';
        $this->menuDepth = 0;
        $this->versions[] = self::MODEL_OUTPUT;

        if ($standalone) {
            $this->publish();
        }
    }

    public function buildWrapin(bool $standalone)
    {
        if (! $this->wrapinData) {
            return;
        }

        if ($standalone) {
            $this->prepare();
        }

        $path = ospath($this->output, self::WRAPIN_OUTPUT);
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        $this->selects[] = [
            'value'   => '/'.self::WRAPIN_OUTPUT,
            'text'    => 'Wrapin',
            'version' => self::WRAPIN_OUTPUT,
        ];

        foreach ($this->wrapinData as $ns => $wrapin) {
            $key = $this->formatDocNamespace($ns);
            $this->appendMenuTree($key, null, $key);
            $this->render($this->docWrapin, ospath($path, "{$key}.md"), $this->formatWrapinDocData($wrapin, $key));
        }

        $readme = ospath($path, self::README);
        if (is_file($readme)) {
            unlink($readme);
        }
        $this->render($this->readme, $readme, ['version' => 'Wrapin List']);
        
        $summary = ospath($ver, self::SUMMARY);
        if (is_file($summary)) {
            unlink($summary);
        }
        $this->render($this->summary, $summary, ['tree' => $this->menuTree, 'readme' => true]);

        $this->menuTree  = '';
        $this->menuDepth = 0;
        $this->versions[] = self::WRAPIN_OUTPUT;

        if ($standalone) {
            $this->publish();
        }
    }

    public function buildHttp(bool $standalone)
    {
        if ($standalone) {
            $this->prepare();
        }

        foreach ($this->apiData as $version => $domain) {
            $ver = ospath($this->output, $version);
            if (! is_dir($ver)) {
                mkdir($ver, 0775, true);
            }
            $this->selects[] = [
                'value'   => "/{$version}",
                'text'    => "API {$version}",
                'version' => $version,
            ];

            foreach (($domain['main'] ?? []) as $key => $data) {
                $title = $data['title'] ?? false;
                if (! $title) {
                    exception('MissingDocTitle');
                }
                $_domain = ospath($ver, $key);
                if (! is_dir($_domain)) {
                    mkdir($_domain, 0775, true);
                }
                $this->appendMenuTree($title, $key);
                $group = $data['group'] ?? [];
                $list  = $data['list']  ?? [];
                $this->genHttpGroup($group, $_domain, $key);
                $this->menuDepth = 1;
                $this->genHttpList($list, $_domain, $key);
                $this->menuDepth = 0;
            }

            $readme = ospath($ver, self::README);
            if (is_file($readme)) {
                unlink($readme);
            }
            $this->render($this->readme, $readme, ['version' => $version]);

            $summary = ospath($ver, self::SUMMARY);
            if (is_file($summary)) {
                unlink($summary);
            }
            $error = ospath($ver, self::DOC_ERROR);
            if (is_file($error)) {
                unlink($error);
            }
            $this->render($this->docError, $error, ['errors' => $this->errorData]);
            
            $appendixesDomain = $domain['appendixes']['domain'] ?? [];
            $_appendixes = ospath($ver, '_appendixes');
            if (! is_dir($_appendixes)) {
                mkdir($_appendixes, 0775, true);
            }

            $_appendixesDomain = [];
            if ($appendixesDomain) {
                foreach ($appendixesDomain as $__domain) {
                    foreach ($__domain as $appendix) {
                        if (! ($path = ($appendix['path'] ?? false))) {
                            exception('MissingAppendixDocFile');
                        }
                        if (! is_file($path)) {
                            exception('AppendixDocFileNotExist', compact('path'));
                        }
                        if (! ($key = ($appendix['key'] ?? false))) {
                            exception('MissingAppendixDocDomainKey');
                        }
                        $_key = ospath($_appendixes, $key);
                        if (! is_dir($_key)) {
                            mkdir($_key, 0775, true);
                        }
                        $href = basename($path);
                        copy($path, ospath($_key, $href));

                        $appendix['href'] = join(DIRECTORY_SEPARATOR, ['_appendixes', $key, $href]);

                        $_appendixesDomain[] = $appendix;
                    }
                }
            }

            unset($appendix);

            $appendixesGlobal = $domain['appendixes']['global'] ?? [];
            foreach ($appendixesGlobal as &$appendix) {
                if (! ($path = ($appendix['path'] ?? false))) {
                    exception('MissingAppendixDocFile');
                }
                if (! is_file($path)) {
                    exception('AppendixDocFileNotExist', compact('path'));
                }
                if (! ($key = ($appendix['key'] ?? false))) {
                    exception('MissingAppendixDocDomainKey');
                }
                $_key = ospath($_appendixes, $key);
                if (! is_dir($_key)) {
                    mkdir($_key, 0775, true);
                }
                $href = basename($path);
                copy($path, ospath($_key, $href));

                $appendix['href'] = join(DIRECTORY_SEPARATOR, ['_appendixes', $key, $href]);
            }

            $this->render($this->summary, $summary, [
                'tree' => $this->menuTree,
                'appendixes' => ['domain' => $_appendixesDomain, 'global' => $appendixesGlobal],
                'errors' => true,
            ]);

            $this->menuTree  = '';
            $this->menuDepth = 0;
        }

        $this->versions = array_merge($this->versions, array_keys($this->apiData));

        if ($standalone) {
            $this->publish();
        }
    }

    public function buildAll()
    {
        $this->prepare();
        $this->buildHttp(false);
        $this->buildWrapin(false);
        $this->buildModel(false);
        $this->buildAssets();
        $this->publish();
    }

    private function buildAssets()
    {
        $assets = ConfigManager::getDomain('docs.assets');
        if (! $assets) {
            return;
        }

        foreach ($assets as $asset) {
            $path = ospath(Kernel::getRoot(), $asset);
            if (! is_file($path)) {
                exception('InvalidDocAssetsPath', compact('path', 'asset'));
            }

            $arr = array_trim_from_string($asset, DIRECTORY_SEPARATOR);
            unset($arr[0]);
            $_asset = ospath($this->output, '__assets', $arr);
            $_dir = dirname($_asset);
            if (! is_dir($_dir)) {
                mkdir($_dir, 0775, true);
            }

            copy($path, $_asset);
        }
    }

    private function publish()
    {
        $builder = ospath($this->output, self::BUILDER);
        if (is_file($builder)) {
            unlink($builder);
        }
        $this->render($this->builder, $builder, ['versions' => $this->versions, 'output' => $this->output]);

        $verindex = ospath($this->output, self::VER_INDEX);
        if (is_file($verindex)) {
            unlink($verindex);
        }
        $this->render($this->verindex, $verindex, ['default' => $this->versions[0] ?? 404]);

        // Foramt book.json versions plugin configs with default version display logic
        foreach ($this->selects as list('version' => $_version)) {
            $_selects  = $this->selects;
            $_versions = array_column($this->selects, 'version');
            $idx = array_search($_version, $_versions);
            if (false !== $idx) {
                $default = $_selects[$idx] ?? [];
                unset($_selects[$idx]);
                array_unshift($_selects, $default);
            }
            $bookjson = ospath($this->output, $_version, self::BOOK_JSON);
            if (is_file($bookjson)) {
                unlink($bookjson);
            }
            $this->render($this->bookjson, $bookjson, ['options' => enjson($_selects)]);
        }
    }

    private function appendMenuTree(string $title, string $folder = null, string $filename = null)
    {
        if (is_null($filename)) {
            $path = '';   // Avoid chapters toggle not working
        } else {
            $filename .= '.md';
            $path = $folder ? join('/', [$folder, $filename]) : $filename;
        }

        $this->menuTree .= sprintf(
            '%s* [%s](%s)%s',
            str_repeat("\t", $this->menuDepth),
            $title,
            $path,
            PHP_EOL
        );
    }

    /**
     * Generate HTTP docs list menus
     *
     * @param array $list: List memus data
     * @param string $dir: List docs directory
     * @param string $path: The markdown doc link path (relative)
     */
    private function genHttpList(array $list, string $dir, string $path)
    {
        foreach ($list as $doc) {
            $route = $doc['route'] ?? false;
            if (! $route) {
                exception('MissingUrlpath');
            }
            $verbs = $doc['verbs'] ?? [];
            if (! $verbs) {
                exception('MissingVerbs');
            }
            $_doc = md5(enjson([$route, $verbs]));
            if (! ($doc['author'] ?? false)) {
                $doc['author'] = 'unknown';
            }

            $_title = $doc['title'] ?? '?';
            $this->appendMenuTree($_title, $path, $_doc);
            $this->render($this->docApi, ospath($dir, "{$_doc}.md"), $doc);
        }
    }

    /**
     * Generate HTTP docs group menus
     *
     * @param array $group: Group memus data
     * @param string $dir: Group docs files save directory (absolute)
     * @param string $path: The markdown doc link path (relative)
     */
    private function genHttpGroup(array $group, string $dir, string $path)
    {
        foreach ($group as $name => $_group) {
            ++$this->menuDepth;
            $_key = join('/', [$path, $name]);
            $_dir = ospath($dir, $name);
            if (! is_dir($_dir)) {
                mkdir($_dir, 0775, true);
            }
            $title = $_group['title'] ?? '?';
            $this->appendMenuTree($title, $_key);
            if ($__group = ($_group['group'] ?? [])) {
                $this->genHttpGroup($__group, $_dir, $_key);
            }
            if ($list = ($_group['list'] ?? [])) {
                ++$this->menuDepth;
                $this->genHttpList($list, $_dir, $_key);
                --$this->menuDepth;
            }
            --$this->menuDepth;
        }
    }

    public function formatModelDocData(array $annotation, string $key)
    {
        $data = [];

        $data['model'] = $annotation['meta']['TITLE'] ?? $key;
        $data['key'] = $key;
        foreach ($annotation['properties'] ?? [] as $name => $options) {
            $data['properties'][] = [
                'name' => $name,
                'type' => $options['TYPE']  ?? null,
                'title' => $options['TITLE'] ?? null,
                'notes' => $options['NOTES'] ?? null,
                'arguments' => $options['__ext__']['ARGUMENT'] ?? [],
            ];
        }

        return $data;
    }

    public function formatWrapinDocData(array $annotation, string $key)
    {
        $data = [];

        $data['wrapin'] = $annotation['meta']['TITLE'] ?? $key;
        $data['key'] = $key;

        foreach ($annotation['properties'] ?? [] as $name => $options) {
            $rules = $options;
            array_unset(
                $rules,
                '__ext__',
                'TITLE',
                'TYPE',
                'NOTES',
                'DEFAULT',
                'COMPATIBLE'
            );

            if ($wrapin = ($rules['WRAPIN'] ?? false)) {
                $rules['WRAPIN'] = $this->formatDocNamespace($wrapin);
            }

            $data['params'][] = [
                'name' => $name,
                'type' => $options['TYPE']  ?? null,
                'title' => $options['TITLE'] ?? null,
                'notes' => $options['NOTES'] ?? null,
                'default' => $options['DEFAULT'] ?? null,
                'compatibles' => $options['COMPATIBLES'] ?? [],
                'validators'  => $rules,
            ];
        }

        return $data;
    }

    private function formatDocNamespace(string $namespace = null)
    {
        if (! $namespace) {
            return null;
        }

        $arr = array_trim_from_string($namespace, '\\');
        unset($arr[0]);

        return join('.', $arr);
    }

    public function render(string $tpl, string $save, array $data = [])
    {
        render_to($data, $tpl, $save, function ($e) use ($tpl) {
            exception('GitBookRenderError', compact('tpl'), $e);
        });
    }

    /**
     * Setter for language
     *
     * @param string $language
     * @return GitBook
     */
    public function setLanguage(string $language)
    {
        if (! (self::SUPPORT_LANG[$this->language] ?? false)) {
            exception('GitBookTemplateLanguageNotSupport', compact('language'));
        }

        $this->language = $language;
    
        return $this;
    }

    /**
     * Setter for apiData
     *
     * @param array $apiData
     * @return GitBook
     */
    public function setApiData(array $apiData)
    {
        $this->apiData = $apiData;
    
        return $this;
    }

    /**
     * Setter for wrapinData
     *
     * @param array $wrapinData
     * @return GitBook
     */
    public function setWrapinData(array $wrapinData)
    {
        $this->wrapinData = $wrapinData;
    
        return $this;
    }

    /**
     * Setter for modelData
     *
     * @param array $modelData
     * @return GitBook
     */
    public function setModelData(array $modelData)
    {
        $this->modelData = $modelData;
    
        return $this;
    }

    /**
     * Setter for errorData
     *
     * @param array $errorData
     * @return GitBook
     */
    public function setErrorData(array $errorData)
    {
        $this->errorData = $errorData;
    
        return $this;
    }

    /**
     * Setter for templates
     *
     * @param string $templates
     * @return GitBook
     */
    public function setTemplates(string $templates)
    {
        $this->templates = $templates;
    
        return $this;
    }

    /**
     * Setter for output
     *
     * @param string $output
     * @return GitBook
     */
    public function setOutput($output)
    {
        $this->output = $output;
    
        return $this;
    }
}
