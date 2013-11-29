<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Command that will validate a yml file syntax and output encountered errors.
 *
 * @author Luis Cordova <cordoval@gmail.com>
 */
class YamlLintCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('yaml:lint')
            ->setDescription('Lints a yaml file and outputs encountered errors')
            ->addArgument('filename')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command lints a yaml file and outputs to stdout
the first encountered syntax error.

<info>php %command.full_name% filename</info>

The command gets the contents of <comment>filename</comment> and validates its syntax.

<info>php %command.full_name% dirname</info>

The command finds all yaml files in <comment>dirname</comment> and validates the syntax
of each yaml file.

<info>cat filename | php %command.full_name%</info>

The command gets the yaml file contents from stdin and validates its syntax.
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $yaml = $this->getContainer()->get('yaml');
        $file = null;
        $filename = $input->getArgument('filename');

        if (!$filename) {
            if (0 !== ftell(STDIN)) {
                throw new \RuntimeException("Please provide a filename or pipe template content to stdin.");
            }

            while (!feof(STDIN)) {
                $file .= fread(STDIN, 1024);
            }

            return $this->validateYamlFile($yaml, $output, $file);
        }

        if (0 !== strpos($filename, '@') && !is_readable($filename)) {
            throw new \RuntimeException(sprintf('File or directory "%s" is not readable', $filename));
        }

        $files = array();
        if (is_file($filename)) {
            $files = array($filename);
        } elseif (is_dir($filename)) {
            $files = Finder::create()->files()->in($filename)->name('*.yml');
        } else {
            $dir = $this->getApplication()->getKernel()->locateResource($filename);
            $files = Finder::create()->files()->in($dir)->name('*.yml');
        }

        $errors = 0;
        foreach ($files as $file) {
            $errors += $this->validateYamlFile($yaml, $output, file_get_contents($file), $file);
        }

        return $errors > 0 ? 1 : 0;
    }

    protected function validateYamlFile(YamlLint $yaml, OutputInterface $output, $contents, $file = null)
    {
        try {
            $yaml->parse($yaml->tokenize($contents, $file ? (string) $file : null));
            $output->writeln('<info>OK</info>'.($file ? sprintf(' in %s', $file) : ''));
        } catch (\Yaml_Error $e) {
            $this->renderException($output, $contents, $e, $file);

            return 1;
        }

        return 0;
    }

    protected function renderException(OutputInterface $output, $contents, \Yaml_Error $exception, $file = null)
    {
        $line =  $exception->getYamlLine();
        $lines = $this->getContext($contents, $line);

        if ($file) {
            $output->writeln(sprintf("<error>KO</error> in %s (line %s)", $file, $line));
        } else {
            $output->writeln(sprintf("<error>KO</error> (line %s)", $line));
        }

        foreach ($lines as $no => $code) {
            $output->writeln(sprintf(
                "%s %-6s %s",
                $no == $line ? '<error>>></error>' : '  ',
                $no,
                $code
            ));
            if ($no == $line) {
                $output->writeln(sprintf('<error>>> %s</error> ', $exception->getRawMessage()));
            }
        }
    }

    protected function getContext($contents, $line, $context = 3)
    {
        $lines = explode("\n", $contents);

        $position = max(0, $line - $context);
        $max = min(count($lines), $line - 1 + $context);

        $result = array();
        while ($position < $max) {
            $result[$position + 1] = $lines[$position];
            $position++;
        }

        return $result;
    }
}
