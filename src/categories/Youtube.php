<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 14, 2017
 */

require_once(__DIR__ . '/../main/Retrieval.php');

class Youtube extends Retrieval
{
    private $base, $url_parameters = [], $nextPage = '';
    private $product_id, $key;

    public function __construct($taskInfo){
        parent::__construct($taskInfo);
        $this->product_id = $taskInfo['product_id'];

        $this->setClientKey();
        $this->setUrlParameters();
    }

    private function setClientKey(){
        $this->key = $_ENV['youtube_client_key'];
    }

    private function setUrlParameters(){
        $this->base = 'https://www.googleapis.com/youtube/v3/commentThreads';

        $this->url_parameters = [
            'key' => $this->key, 'textFormat' => 'plainText', 'part' => 'snippet',
            'videoId' => $this->product_id, 'maxResults' => 100
        ];

        $self = $this->retrievedPage = $this->iterations;
        $last = $this->totalPages = 100; // mock default value (Youtube does not provide page numbers)

        if(!$this->nextPage){
            if($this->iterations > 0){
                $last = $this->totalPages = $self;
            }
//            print_r([$this->iterations.': ', $this->retrievedPage, $this->totalPages]);
        }else{
            $this->url_parameters['pageToken'] = $this->nextPage;
        }

        if($self >= $last){ $this->endProcess = true; }
        return true;
    }

    public function retrieveData(){
        do {
            $this->iterations++;
            parent::makeRequest($this->base, $this->url_parameters);
            $this->processResponse();

            !$this->endProcess ? sleep(abs(rand(3,8))) : null;
        } while (!$this->endProcess and $this->iterations < 3);
//        } while (!$this->endProcess);

        return $this->totalReviews > 0 ? 1 : 0;
    }

    public function processResponse(){
        $this->resource = json_decode(parent::stripChars($this->resource),true);

        $this->nextPage = (!empty($this->resource['nextPageToken']))
                        ? $this->resource['nextPageToken'] : '';
        $this->setUrlParameters();

        if(empty($this->resource['items'])){
            return !$this->endProcess = true;
        }

        parent::beginStorage();
        $count = 0;

        foreach ($this->resource['items'] as $item){
            $snippet = $item['snippet']['topLevelComment']['snippet'];
            $snippet['publishedAt'] = date("Y-m-d", strtotime($snippet['publishedAt']));
            if(empty($snippet['authorDisplayName'])){ continue; }

            $values = [
                $snippet['authorDisplayName'],
                $snippet['publishedAt'],
                $snippet['viewerRating'],
                $snippet['textDisplay'],
                $snippet['authorChannelUrl']
            ];
            parent::storeEntry($values);
            $count++;
        }

        parent::endStorage();

        $this->totalReviews += $count;
        $this->resource = [];
        return null;
    }

}
