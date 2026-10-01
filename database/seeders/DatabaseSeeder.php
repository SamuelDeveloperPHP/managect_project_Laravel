<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (Schema::hasColumn('companies', 'cnpj')) {
            $company = Company::query()->firstOrFail();
            $user = User::query()->where('company_id', $company->id)->firstOrFail();
        } else {
            $company = Company::firstOrCreate(['slug' => 'demo-managect'], ['name' => 'Empresa Demo ManageCT']);
            $user = User::factory()->create([
                'company_id' => $company->id,
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        app(TenantContext::class)->setCompanyId($company->id);

        $project = $company->projects()->firstOrCreate(['code' => 'BLING-PARIDADE'], [
            'name' => 'Paridade com Bling',
            'description' => 'Acompanhamento das funcionalidades do backlog de paridade.',
            'created_by' => $user->id,
        ]);

        foreach ([
            ['BL-01-05', '01 Plataforma SaaS, Planos e Cobrança', 'Cadastro de planos e limites por empresa', 'P0', 'R1', 5],
            ['BL-04-06', '04 Vendas, PDV e Orçamentos', 'Fluxo de pedido com situações customizáveis', 'P0', 'R1', 8],
            ['BL-06-06', '06 Estoque', 'Reserva de estoque por pedido', 'P0', 'R1', 5],
            ['BL-07-10', '07 Financeiro', 'Cobrança real PIX e boleto por provedor', 'P0', 'R1', 8],
            ['BL-08-08', '08 Fiscal', 'Emissão automática de NF por situação do pedido', 'P0', 'R1', 8],
            ['BL-09-01', '09 Integrações e Marketplaces', 'API pública REST com OAuth2 e chaves por empresa', 'P0', 'R2', 8],
        ] as [$code, $epic, $title, $priority, $release, $points]) {
            $project->backlogItems()->firstOrCreate(['code' => $code], compact('epic', 'title', 'priority', 'release', 'points') + ['company_id' => $company->id]);
        }
    }
}
