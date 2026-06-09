<?php
// +----------------------------------------------------------------------
// | @project   BenXinAdmin
// | @mission   代码生成器 — stub 占位符渲染引擎
// | @author    仗键天涯(daxing)
// | @email     3442535897@qq.com
// | @date      2026-06-10 10:00:00
// +----------------------------------------------------------------------

declare(strict_types=1);

namespace generator;

use RuntimeException;

/**
 * stub 渲染：加载 stub 文件并把 `{{ key }}`（占位符两侧空格可有可无）替换为对应内容。
 */
class StubRenderer
{
    private string $stubDir;

    public function __construct(string $stubDir)
    {
        $this->stubDir = rtrim($stubDir, '/\\');
    }

    /**
     * @param array<string,string> $replacements
     */
    public function render(string $stub, array $replacements): string
    {
        $file = $this->stubDir . DIRECTORY_SEPARATOR . $stub . '.stub';
        if (!is_file($file)) {
            throw new RuntimeException("stub 不存在：{$file}");
        }

        $content = (string) file_get_contents($file);
        foreach ($replacements as $key => $value) {
            $content = preg_replace('/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/', $this->quote($value), $content);
        }

        return $content;
    }

    /**
     * 防止替换值里的 `$0`、`\1` 等被 preg_replace 当作反向引用。
     */
    private function quote(string $value): string
    {
        return str_replace(['\\', '$'], ['\\\\', '\\$'], $value);
    }
}
