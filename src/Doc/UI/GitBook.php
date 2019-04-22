<?php

declare(strict_types=1);

namespace Dof\Framework\Doc\UI;

use Throwable;

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

    /** @var string: Doc menus tree */
    private $menuTree  = '';

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
        $this->docWrapin = $docWrapin;
    }

    public function buildModel(bool $standalone)
    {
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
            $this->save(
                ($this->render($this->docModel, $this->formatModelDocData($model, $key))),
                ospath($path, "{$key}.md")
            );
        }

        $this->save(
            $this->render($this->readme, ['version' => 'Data Model']),
            ospath($path, self::README)
        );

        $this->save(
            $this->render($this->summary, ['tree' => $this->menuTree]),
            ospath($path, self::SUMMARY)
        );

        $this->menuTree  = '';
        $this->menuDepth = 0;
        $this->versions[] = self::MODEL_OUTPUT;

        if ($standalone) {
            $this->publish();
        }
    }

    public function buildWrapin(bool $standalone)
    {
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
            $this->save(
                ($this->render($this->docWrapin, $this->formatWrapinDocData($wrapin, $key))),
                ospath($path, "{$key}.md")
            );
        }

        $this->save(
            $this->render($this->readme, ['version' => 'Wrapin List']),
            ospath($path, self::README)
        );

        $this->save(
            $this->render($this->summary, ['tree' => $this->menuTree]),
            ospath($path, self::SUMMARY)
        );

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

            foreach ($domain as $key => $data) {
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
            }

            $this->save(
                $this->render($this->readme, ['version' => $version]),
                ospath($ver, self::README)
            );
            $this->save(
                $this->render($this->summary, ['tree' => $this->menuTree]),
                ospath($ver, self::SUMMARY)
            );

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
        $this->publish();
    }

    private function publish()
    {
        $this->save(
            $this->render($this->builder, ['versions' => $this->versions]),
            ospath($this->output, self::BUILDER)
        );

        $this->save(
            $this->render($this->verindex, ['default' => $this->versions[0] ?? 404]),
            ospath($this->output, self::VER_INDEX)
        );

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
            $this->save(
                $this->render($this->bookjson, ['options' => enjson($_selects)]),
                ospath($this->output, $_version, self::BOOK_JSON)
            );
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
            $this->save(($this->render($this->docApi, $doc)), ospath($dir, "{$_doc}.md"));
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
                'notes' => $options['notes'] ?? null,
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

    private function save(string $content, string $save)
    {
        file_put_contents($save, $content);
    }

    public function render(string $tpl, array $data = []) : string
    {
        if (! is_file($tpl)) {
            exception('RenderTemplateNotExists', compact('tpl'));
        }
        extract($data, EXTR_OVERWRITE);
        try {
            $level = ob_get_level();
            ob_start();

            include $tpl;

            return (string) ob_get_clean();
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            exception('GitBookRenderError', compact('tpl'), $e);
        }
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
