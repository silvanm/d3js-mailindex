<?php
/**
 * @author Silvan
 */

namespace Mpom;


use Google_Service_Gmail;
use Google_Service_Gmail_Message;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateCommand extends Command
{

    protected $records = [];

    protected function configure()
    {
        $defaultQuery = 'from:me after:2011/01/01 -label:Skype-chats';
        $this
            ->setName('generate')
            ->setDescription('Generate mail stats')
            ->addArgument(
                'query',
                InputArgument::OPTIONAL,
                'Query to use.',
                $defaultQuery
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $query = $input->getArgument('query');

        // Get the API client and construct the service object.
        $client  = getClient();
        $service = new Google_Service_Gmail($client);

        $pageToken      = null;
        $messages       = array();
        $opt_param      = array();
        $userId         = 'me';
        $opt_param['q'] = $query;
        $output->writeln("<info>Reading data using query $query</info>");
        do {
            try {
                if ($pageToken) {
                    $opt_param['pageToken'] = $pageToken;
                }

                $messagesResponse = $service->users_messages->listUsersMessages($userId, $opt_param);
                if ($messagesResponse->getMessages()) {
                    $messages  = array_merge($messages, $messagesResponse->getMessages());
                    $pageToken = $messagesResponse->getNextPageToken();
                }
            } catch
            (Exception $e) {
                print 'An error occurred: '.$e->getMessage();
            }
        } while ($pageToken);


        $client->setUseBatch(true);
        $batch = new \Google_Http_Batch($client);

        /** @var Google_Service_Gmail_Message $message */
        $itemsInBatch = 0;

        $progress = new ProgressBar($output, count($messages));
        $progress->start();

        foreach ($messages as $message) {
            $req = $service->users_messages->get($userId, $message->getId(), ['format' => 'metadata']);
            $batch->add($req);
            $progress->advance();

            if ($itemsInBatch >= 200) {
                $this->processBatch($batch);
                $itemsInBatch = 0;
                $batch        = new \Google_Http_Batch($client);
            }
            $itemsInBatch++;
        }

        $progress->finish();
        $output->writeln("<info>Writing results to outputfile</info>");
        file_put_contents("public/data2.json", json_encode($this->records));
    }

    protected function processBatch(\Google_Http_Batch $batch)
    {
        $results = $batch->execute();

        /** @var Google_Service_Gmail_Message $result */
        foreach ($results as $result) {

            if (get_class($result) != 'Google_Service_Gmail_Message') {
                continue;
            }

            /** @var Google_Service_Gmail_MessagePart $message */
            $message = $result->getPayload();
            $headers = $this->convertHeaderInArray($message->getHeaders());

            $date = strtotime($headers['Date']);

            if ( ! empty( $date )) {
                $this->records[] = [
                    'timestamp' => $date,
                    'to' => $headers['To'],
                    'subject' => $headers['Subject']
                ];
            }
        }
    }

    protected function convertHeaderInArray(array $headers)
    {
        $includeOnly = ['Date', 'To', 'Subject'];
        $result      = [];
        foreach ($headers as $header) {
            if (in_array($header->name, $includeOnly)) {
                $result[$header->name] = $header->value;
            }
        }

        return $result;
    }

}