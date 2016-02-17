<?php

namespace Vividin\I18n;

use Gettext\Translations;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;

/**
 * Class PoToCsvCommand
 *
 * Converts PO files to CSV format
 *
 * @package Vividin\I18n
 */
class PoToCsvCommand extends Command
{

    protected $cwd;
    protected $inputDir  = 'in';
    protected $outputDir = 'out';

    protected function configure()
    {
        $this->setName('potocsv')
                ->setDescription('Po to Csv conversion')
                ->addOption(
                        'use-defaults',
                        null,
                        InputOption::VALUE_NONE,
                        'If set, uses enlightened guessing for paths'
                );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->writeSection($output, 'Po to Csv converter');

        $this->cwd = getcwd() . DIRECTORY_SEPARATOR;

        $output->writeln('<info>Using CWD</info> ' . $this->cwd);

        $poFiles      = $this->getInputPoFiles($input, $output);
        $outputFolder = $this->getOutputFolder($input, $output);

        $useDefaults = $input->getOption('use-defaults');

        if ($useDefaults) {
            $output->writeln('<info>Po files</info>:');
            $this->writeList($output, $poFiles);
            $output->writeln([
                    '',
                    '<info>Output folder</info>: ' . $outputFolder,
            ]);
        }

        $poHandles = [];
        foreach ($poFiles as $poFile) {
            $key = basename($poFile, '.po');
            $output->writeln('<info>loading ' . $key . '</info>...');
            $poHandles[$key] = Translations::fromPoFile($poFile);
        }

        $output->writeln('<info>merging po files</info>...');

        $csvArray = [];
        foreach ($poHandles as $language => $poHandle) {
            foreach ($poHandle as $translation) {

                $original    = trim($translation->original);
                $translation = trim($translation->translation);

                if (!isset($csvArray[$original])) {
                    $csvArray[$original] = [
                            $language => $translation,
                    ];
                } elseif (!isset($csvArray[$original][$language])) {
                    $csvArray[$original][$language] = $translation;
                } elseif ($csvArray[$original][$language] != $translation) {
                    $csvArray[$original][$language] = $this->handleConflict($input, $output, $original,
                            $csvArray[$original][$language], $translation);
                }

            }
        }

        $output->writeln('<info>writing csv</info>...');

        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setDelimiter(';');

        $header = ['original'];
        $header = array_merge($header, array_keys($poHandles));

        $writer->insertOne($header);

        foreach ($csvArray as $original => $item) {

            $row = [];
            foreach ($header as $column) {
                if ($column === 'original') {
                    $row[] = $original;
                } else {
                    $row[] = isset($item[$column]) ? $item[$column] : null;
                }
            }
            $writer->insertOne($row);
        }

        $outputFile = $outputFolder . DIRECTORY_SEPARATOR . 'translations.csv';
        file_put_contents($outputFile, $writer->__toString());

        $output->writeln('<info>done. output file is</info> ' . $outputFile);
    }

    /**
     * Tries to guess and prompts user correct input po files
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return array
     */
    private function getInputPoFiles(InputInterface $input, OutputInterface $output)
    {
        $files = glob($this->cwd . $this->inputDir . DIRECTORY_SEPARATOR . '*.po');

        $useDefaults = $input->getOption('use-defaults');
        if ($useDefaults) {
            if (count($files) > 0) {
                return $files;
            } else {
                throw new \RuntimeException('Could not use default input .po files!');
            }
        }

        if (count($files) > 0) {
            $output->writeln([
                    '',
                    'Found following .po files:',
            ]);

            $this->writeList($output, $files);

            $continue = $this->confirm($input, $output, 'Do you want to edit input files?');

            if (!$continue) {
                return $files;
            }
        }

        $files = [];

        while (true) {
            $response = $this->ask($input, $output, 'Give input .po file (enter none to stop)', false,
                    function ($file) use ($files) {

                        if (empty($file)) {
                            return $file;
                        }

                        $file = $this->cwd . $file;

                        if ($file !== "" && !file_exists($file)) {
                            throw new \RuntimeException('File "' . $file . '" does not exist!');
                        }

                        if (in_array($file, $files)) {
                            throw new \RuntimeException('File "' . $file . '" already in list!');
                        }

                        return $file;
                    });

            if (!empty($response)) {
                $files[] = $response;
                $this->writeList($output, $files);
            } else {
                break;
            }
        }

        return $files;
    }

    /**
     * Tries to guess and prompts user correct output folder
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return string
     */
    private function getOutputFolder(InputInterface $input, OutputInterface $output)
    {
        $tryFolder = $this->cwd . $this->outputDir;

        if (is_dir($tryFolder)) {
            $default = $tryFolder;
        } else {
            $default = false;
        }

        $useDefaults = $input->getOption('use-defaults');
        if ($useDefaults) {
            if (!empty($default) > 0) {
                return $default;
            } else {
                throw new \RuntimeException('Could not use default output folder!');
            }
        }

        return $this->ask($input, $output, 'Give output folder', $default, function ($folder) {
            if (!is_dir($folder)) {
                throw new \RuntimeException('File "' . $folder . '" does not exist!');
            }

            return $folder;
        });
    }

    /**
     * Prompts user how conflict should be handled
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $original
     * @param string          $old
     * @param string          $new
     *
     * @return string
     */
    private function handleConflict(InputInterface $input, OutputInterface $output, $original, $old, $new)
    {

        $this->writeSection($output, 'Conflict!', 'bg=red;fg=white');
        $output->writeln([
                sprintf('<info>%s</info> "%s"', 'Original was', $original),
                sprintf('<info>%s</info> "%s"', 'Old was', $old),
                sprintf('<info>%s</info> "%s"', 'New was', $new),
        ]);

        $answer = $this->ask($input, $output, 'choose 1 = new, 2 = old, or give new string', '1');

        if ($answer === '1') {
            return $new;
        } elseif ($answer === '2') {
            return $old;
        } else {
            return $answer;
        }
    }

    /**
     * Writes nicely formatted title block
     *
     * @param OutputInterface $output
     * @param string          $text
     * @param string          $style
     */
    private function writeSection(OutputInterface $output, $text, $style = 'bg=blue;fg=white')
    {
        $output->writeln([
                '',
                $this->getHelper('formatter')->formatBlock('  ' . $text . '  ', $style, true),
                '',
        ]);
    }

    /**
     * Dumps array
     *
     * @param OutputInterface $output
     * @param array           $items
     */
    private function writeList(OutputInterface $output, $items)
    {
        foreach ($items as $item) {
            $output->writeln($item);
        }
    }

    /**
     * Prompts user simple y/n question
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $question
     * @param bool            $default
     *
     * @return bool
     */
    private function confirm(InputInterface $input, OutputInterface $output, $question, $default = false)
    {
        $helper   = $this->getHelper('question');
        $question = new ConfirmationQuestion($this->getQuestion($question . '', $default ? 'Y/n' : 'y/N'), $default);

        return $helper->ask($input, $output, $question);
    }

    /**
     * Returns nicely formatted question text
     *
     * @param string $question
     * @param bool   $default
     * @param string $sep
     *
     * @return string
     */
    private function getQuestion($question, $default = false, $sep = ':')
    {
        return PHP_EOL . ($default
                ? sprintf('<info>%s</info> [<comment>%s</comment>]%s ', $question, $default, $sep)
                : sprintf('<info>%s</info>%s ', $question, $sep));
    }

    /**
     * Prompts user question
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     * @param string          $question
     * @param bool            $default
     * @param callable        $validator
     *
     * @return string
     */
    private function ask(InputInterface $input, OutputInterface $output, $question, $default = false, $validator = null)
    {
        $helper   = $this->getHelper('question');
        $question = new Question($this->getQuestion($question, $default), $default);

        if (is_callable($validator)) {
            $question->setValidator($validator);
        }

        return $helper->ask($input, $output, $question);
    }

}