<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 14, 2017
 */

require_once(__DIR__ . '/../main/Retrieval.php');

class Apple extends Retrieval
{
    private $base, $url_parameters = [], $nextPage = [];
    private $product_id;

    public function __construct($taskInfo){
        parent::__construct($taskInfo);
        $this->product_id = $taskInfo['product_id'];

        $this->setUrlParameters();
    }

    private function getPage($url){
        $url = Url::parseUrl($url);
        $path_components = explode('/', $url['path']);
        $component = array_filter($path_components, function($var){
            return (stristr($var, 'page'));
        });
        $var = explode('page=', reset($component));
        return (!empty($var[1])) ? $var[1] : 0;
    }

    private function setUrlParameters(){
        if(!$this->nextPage){
            $this->base = "https://itunes.apple.com/rss/customerreviews/id={$this->product_id}/json";

        }else{
            $links = []; $rel = $href = '';
            foreach ($this->nextPage as $key => $link){
                if($link['attributes']['rel'] == 'alternate'){ continue; }
                extract($link['attributes']);
                $links[$rel] = [
                    'href' => str_replace('/xml', '/json', $href),
                    'page' => $this->getPage($href)
                ];
            }

            $self = $links['self']['page'];  $prev = $links['previous']['page'];
            $next = $links['next']['page'];  $last = $links['last']['page'];

            if($self < 1){
                $self = ($next < $last) ? $next - 1
                    : ($last > 2 and $prev <= ($next - 1)) ? $prev + 1 : $prev;
            }
            $this->retrievedPage = $self;  $this->totalPages = $last;
//            print_r([$this->iterations.': ', $self, $prev, $next, $last, $this->retrievedPage, $this->totalPages]);

            ($self < $last)
                ? $this->base = str_replace('/xml', '/json', $links['next']['href'])
                : $this->endProcess = true;
        }

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
        $feed = $this->resource['feed'];

        $this->nextPage = $feed['link'];  $this->setUrlParameters();

        if(empty($feed['entry'])){
            return !$this->endProcess = true;
        }

        parent::beginStorage();
        $count = 0;

        foreach ($feed['entry'] as $comment){
            if(empty($comment['author'])){ continue; }
            $values = [
                $comment['author']['name']['label'],
//                    $comment['publishedAt'],
                    date('Y-m-d h:i:s a'),
                $comment['im:rating']['label'],
                $comment['content']['label'],
                $comment['author']['uri']['label']
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
