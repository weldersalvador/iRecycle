// ==========================================
// CONFIGURAÇÃO E DADOS MOCK (Banco de dados falso)
// ==========================================
const USAR_MOCK = true; 
let usuarioLogado = null;

// Banco de dados simulado em memória
const mockDB = {
    estatisticas: { anuncios: 2, lances: 12 },
    meusAnuncios: [
        { id: 1, material: "Garrafas PET", data: "10/10/2026", status: "Em Leilão" },
        { id: 2, material: "Latinhas de Alumínio", data: "12/10/2026", status: "Fechado - Vendido" }
    ],
    leiloesAbertos: [
        { id: 101, material: "Papelão", peso: 50, lanceAtual: 75.00, prazo: "2 dias" },
        { id: 102, material: "Vidro Transparente", peso: 100, lanceAtual: 150.00, prazo: "5 horas" },
        { id: 103, material: "Placas Eletrônicas", peso: 10, lanceAtual: 300.00, prazo: "1 dia" }
    ],
    pontosDescarte: [
        { id: 1, nome: "EcoPonto Centro", endereco: "Rua Halfeld, 100", horario: "08h - 18h", aceita: "Plástico, Metal, Papel" },
        { id: 2, nome: "Recicla JF - Zona Sul", endereco: "Av. Rio Branco, 500", horario: "08h - 17h", aceita: "Vidro, Eletrônicos" }
    ],
    empresasPendentes: [
        { id: 1, nome: "Reciclagem Silva LTDA", cnpj: "12.345.678/0001-90", status: "Pendente" },
        { id: 2, nome: "EcoMetal S.A.", cnpj: "98.765.432/0001-10", status: "Pendente" }
    ]
};

// ==========================================
// ESPERA O HTML CARREGAR PARA ATIVAR OS BOTÕES
// ==========================================
window.onload = function() {

    // 1. Lógica de Login
    const formLogin = document.getElementById('form-login');
    if(formLogin) {
        formLogin.addEventListener('submit', (e) => {
            e.preventDefault();
            const perfil = document.getElementById('login-perfil').value;
            const email = document.getElementById('login-email').value;
            
            usuarioLogado = perfil;
            
            // Esconde tela de login, mostra app
            document.getElementById('tela-login').style.display = 'none';
            document.getElementById('app-principal').style.display = 'block';
            
            // Atualiza a navbar
            document.getElementById('avatar-letra').innerText = email.charAt(0).toUpperCase();
            document.getElementById('perfil-logado-texto').innerText = perfil.toUpperCase();
            
            configurarMenuPorPerfil();
            
            // Vai para a tela inicial padrão do perfil
            if (perfil === 'vendedor') navegarPara('tela-vendedor-visao');
            else if (perfil === 'empresa') navegarPara('tela-empresa-leiloes');
            else if (perfil === 'operador') navegarPara('tela-operador');
            else if (perfil === 'admin') navegarPara('tela-admin');
        });
    }

    // 2. Lógica de Anunciar Material (Modal)
    const formAnuncio = document.getElementById('form-anuncio');
    if(formAnuncio) {
        formAnuncio.addEventListener('submit', (e) => {
            e.preventDefault();
            const nome = document.getElementById('material-nome').value;
            const peso = document.getElementById('material-peso').value;
            const lance = document.getElementById('material-lance').value;
            
            // Simula salvar no banco
            mockDB.meusAnuncios.unshift({ id: Date.now(), material: nome, data: new Date().toLocaleDateString('pt-BR'), status: "Em Leilão" });
            mockDB.estatisticas.anuncios++;
            
            alert(`Sucesso! O material "${nome}" (${peso}kg) foi anunciado com lance mínimo de R$ ${lance}.`);
            fecharModal('modal-anuncio');
            formAnuncio.reset();
            carregarDadosVendedor();
        });
    }

    // 3. Lógica de Dar Lance (Modal)
    const formLance = document.getElementById('form-lance');
    if(formLance) {
        formLance.addEventListener('submit', (e) => {
            e.preventDefault();
            const valor = parseFloat(document.getElementById('lance-valor').value);
            alert(`Lance de R$ ${valor.toFixed(2)} registrado com sucesso! Você será notificado se vencer.`);
            fecharModal('modal-lance');
            formLance.reset();
        });
    }
};

// ==========================================
// FUNÇÕES GLOBAIS (Chamadas pelo HTML)
// ==========================================

function logout() {
    usuarioLogado = null;
    document.getElementById('app-principal').style.display = 'none';
    document.getElementById('tela-login').style.display = 'flex';
}

function configurarMenuPorPerfil() {
    const menu = document.getElementById('menu-navegacao');
    menu.innerHTML = '';
    
    if (usuarioLogado === 'vendedor') {
        menu.innerHTML = `
            <a onclick="navegarPara('tela-vendedor-visao')" class="active">Visão geral</a>
            <a onclick="navegarPara('tela-pontos')">Pontos de descarte</a>
        `;
    } else if (usuarioLogado === 'empresa') {
        menu.innerHTML = `
            <a onclick="navegarPara('tela-empresa-leiloes')" class="active">Leilões Abertos</a>
            <a onclick="navegarPara('tela-pontos')">Pontos de descarte</a>
        `;
    } else if (usuarioLogado === 'admin') {
        menu.innerHTML = `<a onclick="navegarPara('tela-admin')" class="active">Painel Admin</a>`;
    } else if (usuarioLogado === 'operador') {
        menu.innerHTML = `<a onclick="navegarPara('tela-operador')" class="active">Confirmar Entrega</a>`;
    }
}

function navegarPara(telaId) {
    // Esconde todas as telas
    document.querySelectorAll('.conteudo-tela').forEach(tela => tela.style.display = 'none');
    // Mostra a tela desejada
    document.getElementById(telaId).style.display = 'block';
    
    // Carrega os dados específicos da tela
    if (telaId === 'tela-vendedor-visao') carregarDadosVendedor();
    if (telaId === 'tela-empresa-leiloes') carregarLeiloes();
    if (telaId === 'tela-pontos') carregarPontos();
    if (telaId === 'tela-admin') carregarAdmin();
}

function carregarDadosVendedor() {
    document.getElementById('stat-anuncios').innerText = String(mockDB.estatisticas.anuncios).padStart(2, '0');
    document.getElementById('stat-lances').innerText = String(mockDB.estatisticas.lances).padStart(2, '0');
    
    const tbody = document.getElementById('tabela-vendedor-corpo');
    if(tbody) {
        tbody.innerHTML = mockDB.meusAnuncios.map(a => `
            <tr>
                <td>${a.material}</td>
                <td>${a.data}</td>
                <td><span class="badge ${a.status.includes('Fechado') ? 'badge-fechado' : 'badge-ativo'}">${a.status}</span></td>
                <td><button class="btn-cancel" onclick="alert('Ação de visualizar')">Ver</button></td>
            </tr>
        `).join('');
    }
}

function carregarLeiloes() {
    const grid = document.getElementById('grid-leiloes');
    if(grid) {
        grid.innerHTML = mockDB.leiloesAbertos.map(l => `
            <div class="card-item">
                <h3>${l.material}</h3>
                <p><i class="fa-solid fa-weight-hanging"></i> Peso: ${l.peso} kg</p>
                <p><i class="fa-regular fa-clock"></i> Termina em: ${l.prazo}</p>
                <div class="preco">R$ ${l.lanceAtual.toFixed(2)}</div>
                <button class="btn-primary" style="width: 100%; justify-content: center;" onclick="abrirModalLance(${l.id}, '${l.material}')">
                    Dar Lance
                </button>
            </div>
        `).join('');
    }
}

function carregarPontos() {
    const grid = document.getElementById('lista-pontos');
    if(grid) {
        grid.innerHTML = mockDB.pontosDescarte.map(p => `
            <div class="card-item">
                <h3>${p.nome}</h3>
                <p><i class="fa-solid fa-location-dot"></i> ${p.endereco}</p>
                <p><i class="fa-regular fa-clock"></i> ${p.horario}</p>
                <p><strong>Aceita:</strong> ${p.aceita}</p>
            </div>
        `).join('');
    }
}

function carregarAdmin() {
    const tbody = document.getElementById('admin-tabela-empresas');
    if(tbody) {
        tbody.innerHTML = mockDB.empresasPendentes.map(e => `
            <tr>
                <td>${e.nome}</td>
                <td>${e.cnpj}</td>
                <td><span class="badge badge-fechado">${e.status}</span></td>
                <td>
                    <button class="btn-primary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="alert('Empresa Aprovada!')">Aprovar</button>
                </td>
            </tr>
        `).join('');
    }
}

function abrirModal(id) { document.getElementById(id).classList.add('active'); }
function fecharModal(id) { document.getElementById(id).classList.remove('active'); }

function abrirModalLance(id, material) {
    document.getElementById('lance-id').value = id;
    document.getElementById('lance-material-nome').innerText = "Material: " + material;
    abrirModal('modal-lance');
}

function confirmarEntrega() {
    const codigo = document.getElementById('codigo-descarte').value;
    if (!codigo) return alert("Digite um código!");
    
    if (codigo.toUpperCase() === 'REC-1234') {
        alert("✅ Código válido! Entrega confirmada. Transação concluída e pagamento liberado.");
        document.getElementById('codigo-descarte').value = '';
    } else {
        alert("❌ Código inválido. Verifique o código gerado pelo vendedor.");
    }
}

function mudarAbaAdmin(aba) {
    document.querySelectorAll('.admin-conteudo').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(`admin-aba-${aba}`).style.display = 'block';
    event.target.classList.add('active');
}