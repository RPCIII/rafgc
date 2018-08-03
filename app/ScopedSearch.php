<?php
namespace App;

use App\Listing;
use App\SearchFilters;
use Illuminate\Http\Request;
use App\Transformers\ListingTransformer;

class ScopedSearch
{
    protected $request;
    protected $customScope;
    protected $args;
    protected $filters;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $this->filters = new SearchFilters($this->request);
    }

    public function setScope($scopedMethod, $args = [])
    {
        $this->customScope = $scopedMethod;
        $this->args = $args;

        return $this;
    }

    public function get()
    {
        $excludedAreas = [
            'Carrabelle',
            'Apalachicola',
            'Eastpoint',
            'Other Counties',
            'Jackson County',
            'Calhoun County',
            'Holmes County',
            'Washington County',
        ];
        $listing = new Listing();
        $filters = $this->filters;
        $listings = $listing->__call($this->customScope, $this->args)
                    ->when($filters->propertyType, function ($query) use ($filters) {
                        return $query->where('prop_type', 'like', $filters->propertyType);
                    })
                    ->when($filters->area, function ($query) use ($filters) {
                        return $query->where(function ($q) use ($filters){
                            return $q->where('area', $filters->area)
                                     ->orWhere('sub_area', $filters->area)
                                     ->orWhere('city', $filters->area)
                                     ->orWhere('subdivision', $filters->area);
                        });
                    })
                    ->orderBy($filters->sortBy, $filters->orderBy)
                    ->paginate(36);

        return fractal($listings->whereNotIn('area', $excludedAreas), new ListingTransformer);
    }
}