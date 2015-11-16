<?php
/**
 * @author Silvan
 */

namespace Mpom;


use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EnhanceCommand extends Command
{

    protected $records = [];

    protected function configure()
    {
        $this
            ->setName('enhance')
            ->setDescription('Add Domain Colorcode');

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $messages = json_decode(file_get_contents("public/data.json"));
        $result   = [];

        $progress = new ProgressBar($output, count($messages));
        $progress->start();

        foreach ($messages as $message) {
            if (empty($message->to) || !preg_match('/@([a-z.\-]*)/', $message->to, $matches)) {
                $colorcode = 0;
            } else {
                $colorcode = hexdec(substr(md5($matches[1]), 4)) % 360;
            }
            $message->colorcode = $colorcode;
            $result[] = $message;
            $progress->advance();
        }
        $progress->finish();

        file_put_contents("public/data-enhanced.json", json_encode($result));

    }

}