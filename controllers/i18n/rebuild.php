<?php

namespace Common;

use Sifo\Controller;
use Sifo\Domains;
use Sifo\Exception_404;
use function array_keys;
use function array_map;
use function array_multisort;
use function count;
use function file_put_contents;
use function implode;
use function str_replace;
use function trim;

class I18nRebuildController extends Controller
{
    public $is_json = true;
    /** @var I18nTranslatorModel */
    private $i18n_translator_model;

    /**
     * @throws Exception_404
     */
    public function build()
    {
        $this->injectDependencies();

        if (!Domains::getInstance()->getDevMode()) {
            throw new Exception_404('Translation only available while in devel mode');
        }

        $instance = $this->getInstance();
        $languages = $this->getLanguages();
        $translations = $this->getTranslations($languages, $instance);

        $messages_order = array_map('mb_strtolower', array_keys($translations));
        array_multisort($messages_order, SORT_STRING, $translations);

        $result = $this->generateTranslationFiles($languages, $translations, $instance);

        if ($result['result']) {
            return [
                'status' => 'OK',
                'msg' => 'Successfully saved'
            ];
        }

        return [
            'status' => 'KO',
            'msg' => 'Failed to save the translation:' . implode("\n", $result['failed'])
        ];
    }

    private function injectDependencies()
    {
        $this->i18n_translator_model = new I18nTranslatorModel();
    }

    private function getInstance()
    {
        $params = $this->getParams();
        $instance = $this->instance;

        if (isset($params['params'][0])) {
            $instance = $params['params'][0];
        }

        return $instance;
    }

    private function getInheritanceInstance($an_instance)
    {
        $instance_domains = $this->getConfig('domains', $an_instance);
        $instance_inheritance = [];

        if (isset($instance_domains['instance_inheritance'])) {
            $instance_inheritance = $instance_domains['instance_inheritance'];
        }

        return $instance_inheritance;
    }

    private function isParentInstance(array $instance_inheritance)
    {
        $is_parent_instance = false;

        if (empty($instance_inheritance) || (count($instance_inheritance) == 1 && $instance_inheritance[0] == 'common')) {
            $is_parent_instance = true;
        }

        return $is_parent_instance;
    }

    private function getLanguages()
    {
        $language_list = [];
        $languages_in_DB = $this->i18n_translator_model->getDifferentLanguages();

        foreach ($languages_in_DB as $lang) {
            $language_list[] = $lang['l10n'];
        }

        return $language_list;
    }

    private function getTranslations(
        array $languages,
        $an_instance
    ) {
        $translations = [];

        $instance_inheritance = $this->getInheritanceInstance($an_instance);
        $is_parent_instance = $this->isParentInstance($instance_inheritance);

        foreach ($languages as $language) {
            $language_str = $this->i18n_translator_model->getTranslations($language, $an_instance, $is_parent_instance);

            foreach ($language_str as $position => $str) {
                $msgid = $str['message'];
                $msgstr = ($str['translation'] == null ? '' : $str['translation']);
                $translations[$msgid][$language] = $msgstr;
            }
            unset($language_str);
        }

        return $translations;
    }

    private function generateTranslationFiles(
        array $a_languages,
        array $a_translations,
        $an_instance
    ) {
        $failed = [];
        $result = true;

        foreach ($a_languages as $language) {
            $buffer = '';
            $empty_strings_buffer = '';
            $empty[$language] = 0;

            foreach ($a_translations as $msgid => $msgstr) {
                $msgstr[$language] = (isset($msgstr[$language])) ? trim($msgstr[$language]) : null;
                if (!empty($msgstr[$language])) {
                    $item = $this->buildItem($msgid, $msgstr[$language]);
                    $buffer .= $item;
                } else {
                    $item = $this->buildItem($msgid, $msgid);
                    $empty[$language]++;
                    $empty_strings_buffer .= $item;
                }
            }

            $write = $this->writeBuffer($language, $empty, $empty_strings_buffer, $buffer, $an_instance);

            if (!$write) {
                $failed[] = $language;
            }

            $result = $result && $write;
        }

        return ['result' => $result, 'failed' => $failed];
    }

    private function writeBuffer(
        $a_language,
        array $an_empty,
        $an_empty_strings_buffer,
        $a_buffer,
        $an_instance
    ) {
        $buffer = "<?php
        
// Translations file, lang='$a_language'\n// Empty strings: $an_empty[$a_language]\n$an_empty_strings_buffer\n// Completed strings:\n$a_buffer";
        $path = ROOT_PATH . '/instances/' . $an_instance . '/locale/messages_' . $a_language . '.php';

        return @file_put_contents($path, $buffer);
    }

    protected function buildItem(
        $msgid,
        $translation
    ) {
        $item = '$translations["' . str_replace('"', '\"', $msgid) . '"] = ' . '"';
        $item .= str_replace('"', '\"', $translation);
        $item .= "\";\n";

        return $item;
    }
}
