<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Caisse;
use App\Models\DemandeModification;
use Illuminate\Http\Request;

// Demandes de modification d'une caisse approuvée.
// Le pompiste demande, le chef de piste, le gérant ou l'admin accepte (la caisse redevient modifiable) ou refuse.
class DemandeModificationController extends Controller
{
    private function peutDecider(Request $request)
    {
        return in_array((int) optional($request->user())->role_id, [2, 3, 4], true);
    }

    private function charger($q)
    {
        return $q->with([
            'caisse:id,date_caisse,pompe_id,user_id,approuve',
            'caisse.pompe:id,numero',
            'caisse.user:id,name',
            'demandeur:id,name',
            'decideur:id,name',
        ]);
    }

    public function index(Request $request)
    {
        $q = $this->charger(DemandeModification::query())->orderByRaw("statut = 'en_attente' desc")->orderByDesc('created_at');
        if (!$this->peutDecider($request)) {
            $q->where('user_id', optional($request->user())->id); // un pompiste ne voit que ses demandes
        }
        if ($request->filled('caisse_id')) {
            $q->where('caisse_id', $request->caisse_id);
        }
        if ($request->filled('statut')) {
            $q->where('statut', $request->statut);
        }
        return $q->limit(300)->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'caisse_id' => 'required|integer|exists:caisses,id',
            'motif' => 'required|string|min:5|max:2000',
        ]);
        $caisse = Caisse::findOrFail($data['caisse_id']);
        if (!$caisse->approuve) {
            return response()->json(['message' => "Cette caisse n'est pas approuvée : elle peut être modifiée directement."], 422);
        }
        $enCours = DemandeModification::where('caisse_id', $caisse->id)->where('statut', 'en_attente')->exists();
        if ($enCours) {
            return response()->json(['message' => 'Une demande est déjà en attente pour cette caisse.'], 422);
        }
        $demande = DemandeModification::create([
            'caisse_id' => $caisse->id,
            'user_id' => optional($request->user())->id,
            'motif' => $data['motif'],
            'statut' => 'en_attente',
        ]);
        return response()->json($this->charger(DemandeModification::whereKey($demande->id))->first(), 201);
    }

    public function accepter(Request $request, $id)
    {
        if (!$this->peutDecider($request)) {
            abort(403);
        }
        $demande = DemandeModification::findOrFail($id);
        if ($demande->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 422);
        }
        $demande->update([
            'statut' => 'acceptee',
            'traite_par' => $request->user()->id,
            'traite_le' => Carbon::now(),
            'reponse' => substr((string) $request->input('reponse', ''), 0, 2000) ?: null,
        ]);
        // La caisse redevient modifiable par le pompiste ; elle devra être approuvée à nouveau
        Caisse::whereKey($demande->caisse_id)->update(['approuve' => 0]);
        return $this->charger(DemandeModification::whereKey($demande->id))->first();
    }

    public function refuser(Request $request, $id)
    {
        if (!$this->peutDecider($request)) {
            abort(403);
        }
        $demande = DemandeModification::findOrFail($id);
        if ($demande->statut !== 'en_attente') {
            return response()->json(['message' => 'Cette demande a déjà été traitée.'], 422);
        }
        $demande->update([
            'statut' => 'refusee',
            'traite_par' => $request->user()->id,
            'traite_le' => Carbon::now(),
            'reponse' => substr((string) $request->input('reponse', ''), 0, 2000) ?: null,
        ]);
        return $this->charger(DemandeModification::whereKey($demande->id))->first();
    }
}
