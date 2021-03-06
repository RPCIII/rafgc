<?php
namespace App;

use Carbon\Carbon;

class Updater extends Rafgc
{
    protected $lastModified;

    public function __construct()
    {
        parent::__construct();
        $this->lastModified = explode(' ', Listing::pluck('date_modified')->max());
    }

    public function full()
    {
        foreach (self::CLASSES as $class) {
            $this->class = $class;
            $this->update();
        }
    }
    private function update()
    {
        $date = $this->lastModified[0] !== '' ? $this->lastModified[0] : '1970-01-01';
        $time = isset($this->lastModified[1]) ? "T{$this->lastModified[1]}" :  null;
        $offset = 0;
        $maxRowsReached = false;

        while (!$maxRowsReached) {
            $results = $this->rets->Search('Property', $this->class, "DATE_MODIFIED={$date}{$time}+", self::QUERY_OPTIONS);

            echo '---------------------------------------------------------' . PHP_EOL;
            echo 'Class: ' . $this->class . PHP_EOL;
            echo 'Returned Results: ' . $results->getReturnedResultsCount() . PHP_EOL;
            echo 'Total Results: ' . $results->getTotalResultsCount() . PHP_EOL;
            echo 'Offset before this batch: ' . $offset . PHP_EOL;

            foreach ($results as $result) {
                $returnedProperty = new ReturnedProperty($result);
                $updatedListing = $returnedProperty->save();
                $this->fetchPhotosFor($updatedListing);
                $this->fetchGeocodeFor($updatedListing);
                echo 'Updated listing #' . $updatedListing->mls_acct . PHP_EOL;
            }

            $offset += $results->getReturnedResultsCount();
            echo 'Offset after this batch: ' . $offset . PHP_EOL;

            if ($offset >= $results->getTotalResultsCount()) {
                echo 'Final Offset: ' . $offset . PHP_EOL;
                $maxRowsReached = true;
            }
        }
    }

    protected function fetchGeocodeFor($listing)
    {
        $geocode = Location::where('listing_id', $listing->id)->first();
        if ($geocode) {
            $geocode->delete();
        }
        $listingObject = Listing::find($listing->id);
        Location::forListing($listingObject);
    }

    protected function fetchPhotosFor($listing)
    {
        $photos = MediaObject::where('listing_id', $listing->id)->get();
        foreach ($photos as $photo) {
            $photo->delete();
        }
        $objects = $this->rets->Search('Media', 'GFX', 'MLS_ACCT='. $listing->mls_acct, self::QUERY_OPTIONS);
        echo 'Adding / Updating ' . count($objects) . ' media objects to listing #' . ($listing->mls_acct) . PHP_EOL;
        $this->attachMediaObjects($listing, $objects);
    }
}
