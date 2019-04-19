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
    const BOOK_JSON = 'book.json';
    const DOC_TPL   = 'doc';

    /** @var string: UI language */
    private $language = 'zh-CN';

    /** @var array: Templates data */
    private $data;

    /** @var string: Templates directory */
    private $templates;

    /** @var string: Output directory */
    private $output;

    public function build(string $type)
    {
        if (! $this->data) {
            exception('NoDataForGitBookTemplates');
        }

        $templates = ospath($this->templates, $type, 'gitbook', $this->language);
        $summary = ospath($templates, self::SUMMARY);
        if (! is_file($summary)) {
            exception('GitBookSummaryNotFound', compact('summary'));
        }
        $readme = ospath($templates, self::README);
        if (! is_file($readme)) {
            exception('GitBookReadmeNotFound', compact('readme'));
        }
        $doctpl = ospath($templates, self::DOC_TPL);
        if (! is_file($doctpl)) {
            exception('DocTemplateNotFound', compact('doctpl'));
        }

        foreach ($this->data as $version => $domain) {
            $ver = ospath($this->output, $version);
            if (! is_dir($ver)) {
                mkdir($ver, 0775, true);
            }

            $tree = '';
            foreach ($domain as $key => $data) {
                $title = $data['title'] ?? false;
                if (! $title) {
                    exception('MissingDocTitle');
                }
                $_domain = ospath($ver, $key);
                if (! is_dir($_domain)) {
                    mkdir($_domain, 0775, true);
                }
                $tree .= "* [{$title}]({$key}/README.md)\n";
                $group = $data['group'] ?? [];
                $list  = $data['list']  ?? [];
                $this->genGroup($group, $tree, $doctpl, $_domain, $key);
                $this->genList($list, $tree, $doctpl, $_domain, $key);
            }

            $this->save(($this->render($readme, ['version' => $version])), ospath($ver, 'README.md'));
            $this->save(($this->render($summary, ['tree' => $tree])), ospath($ver, 'SUMMARY.md'));
        }
    }

    private function genList(array $list, string &$tree, $doctpl, string $dir, string $key)
    {
        if (! $list) {
            return;
        }
        foreach ($list as $doc) {
            $route = $doc['route'] ?? false;
            if (! $route) {
                exception('MissingUrlpath');
            }
            $verbs = $doc['verbs'] ?? [];
            if (! $verbs) {
                exception('MissingVerbs');
            }
            $_doc  = md5(enjson([$route, $verbs]));
            if (! ($doc['author'] ?? false)) {
                $doc['author'] = 'unknown';
            }

            $_title = $doc['title'] ?? '?';
            $tree .= "\t* [{$_title}]({$key}/{$_doc}.md)\n";
            $this->save(($this->render($doctpl, $doc)), ospath($dir, "{$_doc}.md"));
        }
    }

    private function genGroup(array $group, string &$tree, string $doctpl, string $dir, string $key)
    {
        if (! $group) {
            return;
        }
        foreach ($group as $name => $_group) {
            $_key = join(DIRECTORY_SEPARATOR, [$key, $name]);
            $_dir = ospath($dir, $name);
            if (! is_dir($_dir)) {
                mkdir($_dir, 0775, true);
            }
            $title = $_group['title'] ?? '?';
            $_tree = '';
            $__group = $_group['group'] ?? [];
            $tree .= $this->genGroup($__group, $_tree, $doctpl, $_dir, $_key);
            $list = $_group['list']  ?? [];

            $this->genList($list, $tree, $doctpl, $_dir, $_key);
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
