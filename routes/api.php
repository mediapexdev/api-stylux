<?php

use App\Models\Role;
use App\Models\User;

/**
 * Import des controllers
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\IlotController;
use App\Http\Controllers\UserController;
use Laravel\Sanctum\PersonalAccessToken;
use App\Http\Controllers\PompeController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\CaisseController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\CompteurController;
use App\Http\Controllers\PistoletController;
use App\Http\Controllers\SyntheseController;
use App\Http\Controllers\VenteTpeController;
use App\Http\Controllers\BonClientController;
use App\Http\Controllers\IlotPompeController;
use App\Http\Controllers\CategorieProduitController;
use App\Http\Controllers\LavageController;
use App\Http\Controllers\RecetteController;
use App\Http\Controllers\ReceptionController;
use App\Http\Controllers\ReservoirController;
use App\Http\Controllers\AccessoireController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\RemiseCuveController;
use App\Http\Controllers\CommandeCarController;
use App\Http\Controllers\EncaissementController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\FactureController;
use App\Http\Controllers\DemandeModificationController;
use App\Http\Controllers\LubStockController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\IlotPompePistoletController;
use App\Http\Controllers\ReservoirPistoletController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\EntreMController;
use App\Http\Controllers\InventaireController;
use App\Http\Controllers\PompisteController;
use App\Http\Controllers\ProduitController;
use App\Http\Controllers\SortieMController;
use App\Http\Controllers\TablubController;
use App\Http\Controllers\TabaccController;
use App\Http\Controllers\TabinventaireController;
use App\Http\Controllers\LubrifiantController;
use App\Http\Controllers\MagasinController;
use App\Http\Controllers\LubController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::get('users_list', function () {
    $users = User::with('role')
        ->get()
        ->groupBy(function ($query) {
            return $query->role->nom;
        });
    return $users;
});

Route::post('register', [RegisterController::class, 'register']);

Route::post('login', [RegisterController::class, 'login']);
/**
 * Les Routes de la configuration
 */




Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::apiResource('ilots', IlotController::class);
    Route::apiResource('lubs', LubController::class);
    Route::get('lavage-date/{date}', [LavageController::class, 'lavage_date']);
    Route::get('recette-date/{date}', [RecetteController::class, 'recette_date']);
    Route::get('lubrifiant-date/{date}', [LubrifiantController::class, 'lubrifiant_date']);
    Route::get('accessoire-date/{date}', [AccessoireController::class, 'accessoire_date']);
    Route::get('tablub-date/{date}', [TablubController::class, 'tablub_date']);
    Route::get('tabinventaire-date/{date}', [TabinventaireController::class, 'tabinventaire_date']);
    Route::get('magasin-date/{date}', [MagasinController::class, 'magasin_date']);
    Route::apiResource('recettes', RecetteController::class);
    Route::apiResource('lavages', LavageController::class);
    Route::apiResource('lubrifiants', LubrifiantController::class);
    Route::apiResource('magasins', MagasinController::class);
    Route::apiResource('accessoires', AccessoireController::class);
    Route::apiResource('pompes', PompeController::class);
    Route::apiResource('pistolets', PistoletController::class);
    Route::apiResource('reservoirs', ReservoirController::class);
    Route::apiResource('caisses', CaisseController::class);
    Route::apiResource('bonClients', BonClientController::class);
    Route::apiResource('venteTpes', VenteTpeController::class);
    Route::apiResource('depenses', DepenseController::class);
    Route::apiResource('compteurs', CompteurController::class);
    Route::apiResource('tablubs', TablubController::class);
    Route::apiResource('tabaccs', TabaccController::class);
    Route::apiResource('tabinventaires', TabinventaireController::class);




    Route::get('clients/non-encaisse', [ClientController::class, 'non_encaisse']);
    Route::get('clients/encaisse', [ClientController::class, 'encaisse']);
    Route::get('clients/{id}/encaissements', [ClientController::class, 'encaissements']);
    Route::get('clients/{id}/bons', [ClientController::class, 'bonclients']);
    Route::apiResource('clients', ClientController::class);
    Route::post('factures/{id}/annuler', [FactureController::class, 'annuler']);
    Route::apiResource('factures', FactureController::class)->only(['index', 'show', 'store']);
    Route::get('demandes-modification', [DemandeModificationController::class, 'index']);
    Route::post('demandes-modification', [DemandeModificationController::class, 'store']);
    Route::post('demandes-modification/{id}/accepter', [DemandeModificationController::class, 'accepter']);
    Route::post('demandes-modification/{id}/refuser', [DemandeModificationController::class, 'refuser']);
    // Module lubrifiants (stock temps réel, inventaires)
    Route::get('lub/produits', [LubStockController::class, 'produits']);
    Route::post('lub/produits', [LubStockController::class, 'enregistrerProduit']);
    Route::put('lub/produits/{id}', [LubStockController::class, 'enregistrerProduit']);
    Route::get('lub/stock', [LubStockController::class, 'stock']);
    Route::get('lub/mouvements', [LubStockController::class, 'mouvements']);
    Route::post('lub/mouvements', [LubStockController::class, 'enregistrerMouvement']);
    Route::delete('lub/mouvements/lot/{lot}', [LubStockController::class, 'annulerLot']);
    Route::get('lub/inventaires', [LubStockController::class, 'inventaires']);
    Route::post('lub/inventaires', [LubStockController::class, 'ouvrirInventaire']);
    Route::get('lub/inventaires/{id}', [LubStockController::class, 'inventaire']);
    Route::put('lub/inventaires/{id}', [LubStockController::class, 'enregistrerComptage']);
    Route::post('lub/inventaires/{id}/valider', [LubStockController::class, 'validerInventaire']);
    Route::delete('lub/inventaires/{id}', [LubStockController::class, 'supprimerInventaire']);
    Route::get('lub/rapport', [LubStockController::class, 'rapport']);

    // Module finance (commandes carburants et lubrifiants, plafond, échéances, avoirs TPE)
    Route::get('fin/tableau', [FinanceController::class, 'tableau']);
    Route::get('fin/parametres', [FinanceController::class, 'parametres']);
    Route::put('fin/parametres', [FinanceController::class, 'enregistrerParametres']);
    Route::get('fin/commandes', [FinanceController::class, 'commandes']);
    Route::post('fin/commandes', [FinanceController::class, 'enregistrer']);
    Route::put('fin/commandes/{id}', [FinanceController::class, 'enregistrer']);
    Route::delete('fin/commandes/{id}', [FinanceController::class, 'supprimer']);
    Route::post('fin/commandes/{id}/livrer', [FinanceController::class, 'livrer']);
    Route::post('fin/commandes/{id}/payer', [FinanceController::class, 'payer']);
    Route::post('fin/commandes/{id}/rouvrir', [FinanceController::class, 'rouvrir']);

    Route::apiResource('ilots.pompes', IlotPompeController::class);
    Route::apiResource('categories.produits', CategorieProduitController::class);
    Route::apiResource('ilots.pompes.pistolets', IlotPompePistoletController::class);
    Route::apiResource('reservoirs.pistolets', ReservoirPistoletController::class);
    Route::apiResource('users', UserController::class);
    Route::post('users-update', [UserController::class, 'update_password'])->name('users.update_password');
    Route::post('users/restaurer/{id}', [UserController::class, 'restore'])->name('users.restore');
    Route::delete('users/{id}/definitif', [UserController::class, 'force_destroy'])->name('users.force_destroy');




    Route::apiResource('accessoires', AccessoireController::class);

    Route::get('journees', [CaisseController::class, 'journee']);
    Route::get('journees/{id}', [CaisseController::class, 'show_journee']);
    Route::get('approuver_caisse/{id}', [CaisseController::class, 'approuver_caisse']);
    Route::get('approuver_fiche/{id}', [TablubController::class, 'approuver_fiche']);
    Route::get('approuver_ficheA/{id}', [TabaccController::class, 'approuver_ficheA']);
    Route::get('approuver_stock/{id}', [TabinventaireController::class, 'approuver_stock']);
    Route::get('approuver_synthese/{id}', [SyntheseController::class, 'approuver']);


    Route::apiResource('syntheses', SyntheseController::class);
    Route::apiResource('commande-cars', CommandeCarController::class);
    Route::apiResource('receptions', ReceptionController::class);
    Route::apiResource('remise-cuves', RemiseCuveController::class);
    Route::apiResource('stocks', StockController::class);
    Route::apiResource('encaissements', EncaissementController::class);


    Route::get('caisse_par/{date}', [CaisseController::class, 'caisse_par_date']);

    Route::get('/roles', function (Request $request) {
        return Role::all();
    });


    //Route::apiResource('products', ProductController::class);
    Route::get('recapte', [CaisseController::class, 'recapte']);

    Route::get('vente-commercial', [CaisseController::class, 'ventes_commerciale']);
    Route::get('last-caisse', [CaisseController::class, 'last_caisse']);
    Route::get('pompistes', [CaisseController::class, 'ventes_pompistes']);


    Route::get('rapport/{date}', [CaisseController::class, 'rapport_par_date']);
    Route::post('/logout-all',  function (Request $request) {
        Auth::user()->tokens()->delete();
        return ['message' => 'Logged out'];
    });


    Route::get('admin', [AdminController::class, 'index']);

    Route::post('/logout',  function (Request $request) {

        PersonalAccessToken::findToken($request->bearerToken())->delete();
        return ['message' => 'Logged out'];
    });

    /**
     * Pompiste Details
     */
    Route::get('all-pompistes', [PompisteController::class, 'index']);
    Route::get('detail-pompistes/{id}/{annee}/{mois}', [PompisteController::class, 'detailPompiste']);

    /**
     * Fin Pompistes Details
     */


    /**
     * Magazin
     */
    Route::apiResource('categories', CategorieController::class); 
    Route::apiResource('produits', ProduitController::class);
    Route::apiResource('entres_m', EntreMController::class);
    Route::apiResource('inventaires', InventaireController::class);
    Route::apiResource('sorties_m', SortieMController::class);

    /**
     * Fin Magazin
     */
});
Route::get('facture/{id}', function (Request $request, $id) {

    $mois = $request->query('mois');
    $annee =  $request->query('annee');
    $jour = $request->query('jour');
    return view('download_facture', compact('id', 'mois', 'annee', 'jour'));

    // return ['url'=>$pdf->stream('factute')];
    // return view('welcome', compact('pdf'));
    // dd($pdf->download('invoice.pdf'));
});
