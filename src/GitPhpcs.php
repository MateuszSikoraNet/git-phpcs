<?php

namespace GitPhpcs;

class GitPhpcs
{
    protected $phpcs = '';

    protected $standard = "";

    protected $exclude = "";

    protected $output = '';

    protected $argv = [];

    protected $changes = false;

    protected $origin = false;

    protected $baseBranch = '';

    protected $currentBranch = '';

    protected $branches = '';

    protected $endTextStyle = "\033[0m";

    protected $boldTextStyle = "\033[1m";

    protected $redTextColour = "\033[0;31m";

    protected $greenTextColour = "\033[0;32m";

    protected $yellowTextColour = "\033[0;33m";

    public function __construct(array $argv)
    {
        if (is_null(shell_exec('git status'))) {
            die;
        }

        $this->phpcs = realpath(dirname(__FILE__) . '/../../../bin/phpcs');
        if (is_null(shell_exec($this->phpcs))) {
            die;
        }

        $this->argv = $argv;

        if (in_array('--help', $this->argv)) {
            $this->getHelp();
            die;
        }

        if (in_array('--changes', $this->argv)) {
            $this->changes = true;
        }

        if (in_array('--origin', $this->argv)) {
            $this->origin = true;
        }

        if (in_array('--standard', $this->argv)) {
            $this->standard = $this->argv[array_search('--standard', $this->argv) + 1];
        } elseif (shell_exec('test -e ~/standard.phpcs && echo 1')) {
            $this->standard = '$(cat ~/standard.phpcs)';
        }

        if (in_array('--exclude', $this->argv)) {
            $this->exclude = $this->argv[array_search('--exclude', $this->argv) + 1];
        } elseif (shell_exec('test -e ~/exclude.phpcs && echo 1')) {
            $this->exclude = '$(cat ~/exclude.phpcs)';
        }

        if (in_array('--current-branch', $this->argv)) {
            $this->currentBranch = $this->argv[array_search('--current-branch', $this->argv) + 1];
        } else {
            $this->currentBranch = shell_exec(
                'git branch --show-current'
            );
            $this->currentBranch = trim(preg_replace('/\s\s+/', '', $this->currentBranch));
        }
        $this->currentBranch = $this->origin ? 'origin/' . $this->currentBranch : $this->currentBranch;

        if (in_array('--base-branch', $this->argv)) {
            $this->baseBranch = $this->argv[array_search('--base-branch', $this->argv) + 1];
            $this->baseBranch = $this->origin ? 'origin/' . $this->baseBranch : $this->baseBranch;
        } else {
            $this->baseBranch = shell_exec(
                "git merge-base --fork-point develop {$this->currentBranch}"
            );
            $this->baseBranch = trim(preg_replace('/\s\s+/', '', $this->baseBranch));
        }

        $this->branches = $this->changes ? '' : $this->baseBranch . ' ' . $this->currentBranch;
    }

    protected function getFiles()
    {
        $files = shell_exec(
            'git diff --name-only ' . $this->branches
        );
        return array_filter(explode(PHP_EOL, $files));
    }

    protected function getChangedLines(array $files)
    {
        $changedLines = [];

        foreach ($files as $file) {
            $changes = shell_exec(
                'git diff -U0 ' . $this->branches . ' ' . $file
                . ' | grep -Po "^@@ (.*) @@" | grep -Po "\+(.*) @@$"'
            );
            $changes = str_replace(['+', ' @@'], '', $changes);
            $changes = array_filter(explode(PHP_EOL, $changes));

            foreach ($changes as $change) {
                $change = explode(",", $change);

                if (isset($change[1])) {
                    $start = $change[0];
                    $end = $start + $change[1] - 1;

                    foreach (range($start, $end) as $line) {
                        $lines[$line] = null;
                    }
                } else {
                    $lines[$change[0]] = null;
                }
            }

            $changedLines[realpath($file)] = array_keys($lines);
        }

        return $changedLines;
    }

    protected function getViolationsOnFiles(array $files)
    {
        $standard = $this->standard ? ' --standard=' . $this->standard : $this->standard;
        $exclude = $this->exclude ? ' --exclude=' . $this->exclude : $this->exclude;
        return json_decode(
            shell_exec($this->phpcs . ' -s --report=json' . $standard . $exclude
                . ' ' . implode(' ', $files)),
            true
        );
    }

    protected function getViolationsOnChangedLines(array $violationsOnFiles, array $changedLines)
    {
        $violationsOnChangedLines = [];

        foreach ($violationsOnFiles['files'] as $file => $violations) {
            foreach ($violations['messages'] as $violation) {
                if (in_array($violation['line'], $changedLines[$file])) {
                    $violationsOnChangedLines[$file][$violation['line']] = $violation;
                }
            }
        }

        return $violationsOnChangedLines;
    }

    protected function setOutput(array $violationsOnChangedLines)
    {
        foreach ($violationsOnChangedLines as $file => $lines) {
            $this->output .= "\n{$this->boldTextStyle}FILE: {$file}{$this->endTextStyle}\n";
            foreach ($lines as $line => $violation) {
                $type = $violation['type'] == 'ERROR'
                    ? $this->redTextColour . $violation['type'] . $this->endTextStyle
                    : $this->yellowTextColour . $violation['type'] . $this->endTextStyle;
                $this->output .= "LINE $line ($type) {$violation['message']} ({$violation['source']})\n";
            }
        }
    }

    public function run()
    {
        $files = $this->getFiles();
        if (empty($files)) {
            $this->output = "{$this->redTextColour}There are no files to check.{$this->endTextStyle}\n";
            return;
        }

        $changedLines = $this->getChangedLines($files);
        if (empty($changedLines)) {
            $this->output = "{$this->redTextColour}There are no lines to check.{$this->endTextStyle}\n";
            return;
        }

        $violationsOnFiles = $this->getViolationsOnFiles($files);
        if (empty($violationsOnFiles)) {
            $this->output = "{$this->greenTextColour}There are no violations on changed files.{$this->endTextStyle}\n";
            return;
        }

        $violationsOnChangedLines = $this->getViolationsOnChangedLines($violationsOnFiles, $changedLines);
        if (empty($violationsOnChangedLines)) {
            $this->output = "{$this->greenTextColour}There are no violations on changed lines.{$this->endTextStyle}\n";
            return;
        }

        $this->setOutput($violationsOnChangedLines);
    }

    public function getHelp()
    {
        echo "{$this->boldTextStyle}usage: git-phpcs [--help] [--changes] [--origin]\n"
            . "                 [--standard <standards>] [--exclude <excludes>]\n"
            . "                 [--current-branch <branch>] [--base-branch <branch>]{$this->endTextStyle}\n\n"
            . "{$this->boldTextStyle}These are common commands used in various situations:{$this->endTextStyle}\n\n"
            . "{$this->boldTextStyle}Compare current branch with fork point of develop:{$this->endTextStyle}\n"
            . "   git-phpcs\n\n"
            . "{$this->boldTextStyle}Compare uncommitted changes with current branch:{$this->endTextStyle}\n"
            . "   git-phpcs --changes\n\n"
            . "{$this->boldTextStyle}Compare current branch with fork point of develop on the remote repository:\n"
            . $this->endTextStyle
            . "   git-phpcs --origin\n\n"
            . "{$this->boldTextStyle}Set coding standards for PHP CodeSniffer:{$this->endTextStyle}\n"
            . "   git-phpcs --standard Generic,PSR1,PSR12\n"
            . "   OR\n"
            . "   nano ~/standard.phpcs\n\n"
            . "{$this->boldTextStyle}Exclude coding standards for PHP CodeSniffer:{$this->endTextStyle}\n"
            . "   git-phpcs --exclude Generic.WhiteSpace.DisallowSpaceIndent,Generic.WhiteSpace.DisallowTabIndent\n"
            . "   OR\n"
            . "   nano ~/exclude.phpcs\n\n"
            . "{$this->boldTextStyle}Set current branch:{$this->endTextStyle}\n"
            . "   git-phpcs --current-branch <branch>\n\n"
            . "{$this->boldTextStyle}Set base branch:{$this->endTextStyle}\n"
            . "   git-phpcs --base-branch <branch>\n\n";
    }

    public function getOutput()
    {
        echo $this->output;
    }
}
