<?php
/**
 * Author: Nwosu Cyprian C
 * Date: March 14, 2017
 */

require_once(__DIR__ . '/../main/Retrieval.php');

class Amazon extends Retrieval
{
    private $base, $url_parameters = [], $nextPage = 1;
    private $product_id, $product_name, $last_entry_author = '';

    public function __construct($taskInfo){
        parent::__construct($taskInfo);
        $this->product_id = $taskInfo['product_id'];
        $this->product_name = $taskInfo['product_name'];

        $this->setUrlParameters();
    }

    private function getPage(){
        if(!$this->resource){ return 0; }
        $current_page = $last_page = 0;

        $dom = new DOMDocument();
        @$dom->loadHTML($this->resource);
        $xpath = new DOMXPath($dom);

        $all_pages = "//ul[contains(@class, 'a-pagination')]/li";
        $all_pages_li = $xpath->evaluate($all_pages);
        if($all_pages_li and $count = $all_pages_li->length){
            $last_page_li = $all_pages_li->item($count - 2);
            $last_page = $this->stripChars($last_page_li->nodeValue);
        }

        $current_page_li = "//ul[contains(@class, 'a-pagination')]/li[contains(@class, 'a-selected')]";
        $current_page_li = $xpath->evaluate($current_page_li);
        if($current_page_li and $count = $current_page_li->length){
            $current_page_li = $current_page_li->item(0);
            $current_page = $this->stripChars($current_page_li->nodeValue);
        }

        return [$current_page, $last_page];
    }

    private function setUrlParameters(){
        $this->base = "https://www.amazon.com/{$this->product_name}/product-reviews"
                    . "/{$this->product_id}/ref=cm_cr_getr_d_paging_btm_{$this->nextPage}";

        $this->url_parameters = [
            'ie' => 'UTF8', 'reviewerType' => 'all_reviews',
            'pageSize' => 50, 'sortBy' => 'recent'
        ];

        list($self, $last) = $this->getPage();
        $this->retrievedPage = $self;   $this->totalPages = $last;

        if(!$this->nextPage){
            if($this->iterations > 0){ $last = $this->totalPages = $self; }
//            print_r([$this->iterations.': ', $this->retrievedPage, $this->totalPages]);
        }else{
            $this->url_parameters['pageNumber'] = $this->nextPage;
        }

        if($self and $self >= $last){
            $this->endProcess = true;
//            die(json_encode([$this->endProcess, $self, $last]));
        }
        return true;
    }

    public function retrieveData(){
        do {
            $this->iterations++;
            parent::makeRequest($this->base, $this->url_parameters);
            $this->processResponse();

            !$this->endProcess ? sleep(abs(rand(37,58))) : null;
        } while (!$this->endProcess and $this->iterations < 3);
//        } while (!$this->endProcess);

        return $this->totalReviews > 0 ? 1 : 0;
    }

    public function processResponse(){
        $base_path = "//div[contains(@data-hook,'review')]/div/div";

        $desc = "[@data-hook='review-{columnName}']";
        $rel_paths = [
            'author' => "/span{$desc}/a",
            'title' => "/a{$desc}",
            'star-rating' => "/a/i{$desc}/span",
            'date' => "/span{$desc}",
            'body' => "/span{$desc}",
        ];

        $comments = parent::parseHtml($base_path, $rel_paths);

        if(!$comments or !is_array($comments) or empty(end($comments)['author'])){
            $this->nextPage = 0;
            return !$this->endProcess = true;
        }

        $this->nextPage++;   $this->setUrlParameters();

        parent::beginStorage();
        $count = 0;

        foreach($comments as $comment){
            $comment['date'] = preg_replace('/ *on * /', '', $comment['date']);
            $comment['date'] = date("Y-m-d", strtotime($comment['date']));

            preg_match('/ *([\d]).\d *out *of *([\d])/', $comment['star-rating'], $rating);
//            $comment['star-rating'] = $rating[1].' of '.$rating[2];
            $comment['star-rating'] = $rating[1];

            $values = [
                $comment['author'],   $comment['date'],   $comment['star-rating'],
                $comment['body'],     $comment['url']
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
