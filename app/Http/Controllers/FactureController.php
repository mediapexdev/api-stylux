<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Client;
use App\Models\Facture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Factures des clients crédit.
// Une facture émise n'est jamais modifiée ni supprimée : on peut seulement l'annuler (le numéro reste utilisé).
class FactureController extends Controller
{
    public function index(Request $request)
    {
        $q = Facture::with('client:id,nom', 'user:id,name')->orderByDesc('annee')->orderByDesc('sequence');
        if ($request->filled('client_id')) {
            $q->where('client_id', $request->client_id);
        }
        if ($request->filled('annee')) {
            $q->where('annee', $request->annee);
        }
        return $q->get();
    }

    public function show(Facture $facture)
    {
        return $facture->load('client:id,nom', 'user:id,name');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id' => 'required|integer|exists:clients,id',
            'date_debut' => 'nullable|date',
            'date_fin' => 'nullable|date',
            'lignes' => 'required|array|min:1',
            'lignes.*.bon_id' => 'nullable|integer',
            'lignes.*.date' => 'nullable|string',
            'lignes.*.designation' => 'nullable|string|max:255',
            'lignes.*.montant' => 'required|numeric',
        ]);

        $client = Client::findOrFail($data['client_id']);
        $lignes = array_map(function ($l) {
            return [
                'bon_id' => $l['bon_id'] ?? null,
                'date' => isset($l['date']) ? substr($l['date'], 0, 10) : null,
                'designation' => $l['designation'] ?? (isset($l['bon_id']) ? 'Bon carburant n° ' . $l['bon_id'] : 'Carburant'),
                'montant' => round((float) $l['montant'], 2),
            ];
        }, $data['lignes']);
        $montant = round(array_sum(array_column($lignes, 'montant')), 2);
        $now = Carbon::now();
        $annee = (int) $now->year;

        $facture = DB::transaction(function () use ($data, $client, $lignes, $montant, $now, $annee, $request) {
            // Verrou sur l'année en cours pour garantir une numérotation sans doublon
            $max = Facture::where('annee', $annee)->lockForUpdate()->max('sequence');
            $sequence = ((int) $max) + 1;
            return Facture::create([
                'numero' => sprintf('FAC-%d-%04d', $annee, $sequence),
                'annee' => $annee,
                'sequence' => $sequence,
                'client_id' => $client->id,
                'user_id' => optional($request->user())->id,
                'date_facture' => $now->toDateString(),
                'date_debut' => $data['date_debut'] ?? null,
                'date_fin' => $data['date_fin'] ?? null,
                'montant' => $montant,
                'lignes' => $lignes,
                'client_snapshot' => [
                    'nom' => $client->nom,
                    'telephone' => $client->telephone,
                    'email' => $client->email,
                    'adresse' => $client->adresse,
                ],
                'statut' => 'emise',
            ]);
        });

        return response()->json($facture->load('client:id,nom', 'user:id,name'), 201);
    }

    public function annuler(Request $request, $id)
    {
        $facture = Facture::findOrFail($id);
        $facture->update([
            'statut' => 'annulee',
            'motif_annulation' => substr((string) $request->input('motif', ''), 0, 255) ?: null,
        ]);
        return $facture;
    }
}
