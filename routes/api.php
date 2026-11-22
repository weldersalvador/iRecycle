<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmpresaController;
use App\Http\Controllers\Api\OperadorController;
use App\Http\Controllers\Api\PublicoController;
use App\Http\Controllers\Api\VendedorController;
use Illuminate\Support\Facades\Route;

// Públicas
Route::post('cadastro', [AuthController::class, 'cadastro']);
Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Autenticadas (qualquer perfil)
Route::middleware('auth.token')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('pontos', [PublicoController::class, 'pontos']);
    Route::get('categorias', [PublicoController::class, 'categorias']);
    Route::get('notificacoes', [PublicoController::class, 'notificacoes']);
    Route::post('notificacoes/lidas', [PublicoController::class, 'marcarLidas']);

    Route::middleware('perfil:vendedor')->prefix('vendedor')->group(function () {
        Route::get('resumo', [VendedorController::class, 'resumo']);
        Route::post('anuncios', [VendedorController::class, 'store']);
        Route::post('anuncios/{anuncio}/cancelar', [VendedorController::class, 'cancelar']);
        Route::get('anuncios/{anuncio}/lances', [VendedorController::class, 'lances']);
    });

    Route::middleware('perfil:empresa')->prefix('empresa')->group(function () {
        Route::get('leiloes', [EmpresaController::class, 'leiloes']);
        Route::post('leiloes/{anuncio}/lances', [EmpresaController::class, 'lance']);
        Route::get('lances', [EmpresaController::class, 'meusLances']);
        Route::get('compras', [EmpresaController::class, 'compras']);
        Route::post('compras/{transacao}/pagar', [EmpresaController::class, 'pagar']);
    });

    Route::middleware('perfil:operador')->prefix('operador')->group(function () {
        Route::post('entregas/confirmar', [OperadorController::class, 'confirmarEntrega']);
    });

    Route::middleware('perfil:admin')->prefix('admin')->group(function () {
        Route::get('empresas', [AdminController::class, 'empresas']);
        Route::post('empresas/{empresa}/aprovar', [AdminController::class, 'aprovar']);
        Route::post('empresas/{empresa}/recusar', [AdminController::class, 'recusar']);

        Route::get('categorias', [AdminController::class, 'categorias']);
        Route::post('categorias', [AdminController::class, 'salvarCategoria']);
        Route::put('categorias/{categoria}', [AdminController::class, 'atualizarCategoria']);

        Route::get('pontos', [AdminController::class, 'pontos']);
        Route::post('pontos', [AdminController::class, 'salvarPonto']);
        Route::put('pontos/{ponto}', [AdminController::class, 'atualizarPonto']);

        Route::get('operadores', [AdminController::class, 'operadores']);
        Route::post('operadores', [AdminController::class, 'salvarOperador']);
        Route::put('operadores/{operador}', [AdminController::class, 'atualizarOperador']);

        Route::get('anuncios', [AdminController::class, 'anuncios']);
        Route::post('anuncios/{anuncio}/remover', [AdminController::class, 'removerAnuncio']);
        Route::get('usuarios', [AdminController::class, 'usuarios']);
        Route::post('usuarios/{usuario}/bloquear', [AdminController::class, 'bloquear']);
        Route::post('usuarios/{usuario}/desbloquear', [AdminController::class, 'desbloquear']);
    });
});
