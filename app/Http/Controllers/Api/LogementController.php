<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Models\Logement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LogementController extends Controller
{
    /**
     * Get all accommodations with filtering
     */
    public function index(Request $request)
    {
        return $this->listing($request, Logement::with(['agence']));
    }

    /**
     * Agency accounts: only the accommodations of the agency they own
     * (route protected by auth:sanctum + agency middleware), same filters as index().
     */
    public function mine(Request $request)
    {
        $agencyId = $request->user()->agence->id_agence;

        return $this->listing($request, Logement::with(['agence'])->where('id_agence', $agencyId));
    }

    /**
     * Filtering, sorting, pagination and JSON shape shared by index() and mine().
     */
    private function listing(Request $request, Builder $query)
    {
        // Apply filters
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        if ($request->has('statut') && $request->statut !== '') {
            $query->where('statut', $request->statut);
        }

        if ($request->has('min_price') && $request->min_price !== '') {
            $query->where('tarif_amount', '>=', $request->min_price);
        }

        if ($request->has('max_price') && $request->max_price !== '') {
            $query->where('tarif_amount', '<=', $request->max_price);
        }

        if ($request->has('capacite') && $request->capacite !== '') {
            $query->where('capacite', '>=', $request->capacite);
        }

        if ($request->has('search') && $request->search !== '') {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('titre', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('adresse', 'LIKE', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        
        $allowedSortFields = ['created_at', 'tarif_amount', 'capacite', 'titre'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = $request->get('per_page', 12);
        $perPage = min($perPage, 50); // Max 50 items per page

        $logements = $query->paginate($perPage);

        // Transform the data properly
        $transformedAccommodations = $logements->getCollection()->map(function ($logement) {
            return [
                'id' => $logement->id_logement,
                'titre' => $logement->titre,
                'description' => $logement->description,
                'type' => $logement->type,
                'capacite' => $logement->capacite,
                'adresse' => $logement->adresse,
                'photo_url' => $logement->photo_url,
                'statut' => $logement->statut,
                'tarif_amount' => $logement->tarif_amount,
                'tarif_unit' => $logement->tarif_unit,
                'devise' => $logement->devise,
                'created_at' => $logement->created_at,
                'agence' => [
                    'id' => $logement->agence->id_agence,
                    'nom' => $logement->agence->nom,
                    'email' => $logement->agence->email,
                    'score_classement' => $logement->agence->score_classement,
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $transformedAccommodations,
                'current_page' => $logements->currentPage(),
                'last_page' => $logements->lastPage(),
                'per_page' => $logements->perPage(),
                'total' => $logements->total(),
            ],
            'meta' => [
                'current_page' => $logements->currentPage(),
                'last_page' => $logements->lastPage(),
                'per_page' => $logements->perPage(),
                'total' => $logements->total(),
            ]
        ]);
    }

    /**
     * Get accommodation by ID
     */
    public function show($id)
    {
        $logement = Logement::with(['agence', 'reservations'])->find($id);

        if (!$logement) {
            return response()->json([
                'success' => false,
                'message' => 'Accommodation not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $logement->id_logement,
                'titre' => $logement->titre,
                'description' => $logement->description,
                'type' => $logement->type,
                'capacite' => $logement->capacite,
                'adresse' => $logement->adresse,
                'photo_url' => $logement->photo_url,
                'statut' => $logement->statut,
                'tarif_amount' => $logement->tarif_amount,
                'tarif_unit' => $logement->tarif_unit,
                'devise' => $logement->devise,
                'created_at' => $logement->created_at,
                'agence' => [
                    'id' => $logement->agence->id_agence,
                    'nom' => $logement->agence->nom,
                    'email' => $logement->agence->email,
                    'score_classement' => $logement->agence->score_classement,
                ],
                'reservations_count' => $logement->reservations->count(),
            ]
        ]);
    }

    /**
     * Get filter options for frontend
     */
    public function filters()
    {
        $types = Logement::distinct()->pluck('type')->filter()->values();
        $maxPrice = Logement::max('tarif_amount') ?? 1000;
        $maxCapacity = Logement::max('capacite') ?? 10;

        return response()->json([
            'success' => true,
            'data' => [
                'types' => $types,
                'price_range' => [
                    'min' => 0,
                    'max' => $maxPrice
                ],
                'capacity_range' => [
                    'min' => 1,
                    'max' => $maxCapacity
                ]
            ]
        ]);
    }
}