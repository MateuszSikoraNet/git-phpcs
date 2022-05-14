## Installation

### Add repository
```
"repositories": [
    {
        "type": "vcs",
        "url": "https://github.com/MateuszSikoraNet/git-phpcs"
    }
]
```
### Install
```
composer global require mateuszsikoranet/git-phpcs:dev-master
```

## Usage

```
git-phpcs [--help] [--changes] [--origin]
          [--standard <standards>] [--exclude <excludes>]
          [--current-branch <branch>] [--base-branch <branch>]

These are common commands used in various situations:

Compare current branch with fork point of develop:
   git-phpcs

Compare uncommitted changes with current branch:
   git-phpcs --changes

Compare current branch with fork point of develop on the remote repository:
   git-phpcs --origin

Set coding standards for PHP CodeSniffer:
   git-phpcs --standard Generic,PSR1,PSR12
   OR
   nano ~/standard.phpcs

Exclude coding standards for PHP CodeSniffer:
   git-phpcs --exclude Generic.WhiteSpace.DisallowSpaceIndent,Generic.WhiteSpace.DisallowTabIndent
   OR
   nano ~/exclude.phpcs
   
Set current branch:
   git-phpcs --current-branch <branch>

Set base branch:
   git-phpcs --base-branch <branch>
```