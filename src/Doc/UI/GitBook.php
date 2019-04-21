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
    const DOC_TPL = 'doc';
    const BUILDER = 'build.sh';
    const BOOK_JSON = 'book.json';
    const VER_INDEX = 'index.html';

    /** @var string: UI language */
    private $language = 'zh-CN';

    /** @var array: Templates data */
    private $data;

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

    /** @var string: Doc template path */
    private $doctpl;

    /** @var strign: Doc menus tree */
    private $menuTree  = '';

    /** @var int: Doc menus depth */
    private $menuDepth = 0;

    public function prepare(string $type)
    {
        if (! $this->data) {
            exception('NoDataForGitBookTemplates');
        }
        $template = ospath($this->templates, 'gitbook', $type, 'lang', $this->language);
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
        $doctpl = ospath($template, self::DOC_TPL);
        if (! is_file($doctpl)) {
            exception('DocTemplateNotFound', compact('doctpl'));
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
        $this->doctpl   = $doctpl;
    }

    public function buildHttp()
    {
        $this->prepare('http');

        $selects = [];
        foreach ($this->data as $version => $domain) {
            $ver = ospath($this->output, $version);
            if (! is_dir($ver)) {
                mkdir($ver, 0775, true);
            }
            $selects[] = [
                'value'   => "/{$version}",
                'text'    => "HTTP API {$version}",
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

        $this->save(
            $this->render($this->verindex, ['default' => $version]),
            ospath($this->output, self::VER_INDEX)
        );
        $this->save(
            $this->render($this->builder, ['versions' => array_keys($this->data)]),
            ospath($this->output, self::BUILDER)
        );

        foreach ($selects as list('version' => $_version)) {
            $_selects  = $selects;
            $_versions = array_column($selects, 'version');
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

    private function appendMenuTree(string $title, string $folder, string $filename = null)
    {
        if (is_null($filename)) {
            $filename = 'README';
        }

        $filename .= '.md';

        $this->menuTree .= sprintf(
            '%s* [%s](%s/%s)%s',
            str_repeat("\t", $this->menuDepth),
            $title,
            $folder,
            $filename,
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
            $this->save(($this->render($this->doctpl, $doc)), ospath($dir, "{$_doc}.md"));
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
     * Setter for data
     *
     * @param array $data
     * @return GitBook
     */
    public function setData(array $data)
    {
        $this->data = $data;
    
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
