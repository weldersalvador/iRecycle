// ==========================================
// iRecycle - Front-end integrado com a API Laravel (/api)
// ==========================================
const API = '/api';
let token = sessionStorage.getItem('irecycle_token');
let usuarioLogado = null;   // perfil do usuário logado
let leilaoEmLance = null;   // id do leilão aberto no modal de lance
let leiloesPorId = {};      // id -> nome do material (para o modal de lance)

const ROTULO_STATUS = {
    aberto: 'Em Leilão', vendido: 'Fechado - Vendido', sem_lances: 'Fechado - Sem lances', cancelado: 'Cancelado',
    aguardando_entrega: 'Aguardando entrega', concluida: 'Concluída', pendente: 'Pendente', pago: 'Pago'
};

// ---------- utilidades ----------
function esc(valor) {
    const d = document.createElement('div');
    d.textContent = valor ?? '';
    return d.innerHTML;
}
const brl = n => 'R$ ' + Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dataHora = iso => new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
const rotulo = s => ROTULO_STATUS[s] || s;

async function api(caminho, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json' };
    if (token) headers.Authorization = 'Bearer ' + token;
    if (body && !(body instanceof FormData)) {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
    }

    const resp = await fetch(API + caminho, { method, headers, body });
    let dados = null;
    try { dados = await resp.json(); } catch (e) { /* resposta sem corpo */ }

    if (resp.status === 401 && token) {
        voltarParaLogin();
        throw new Error('Sua sessão expirou. Entre novamente.');
    }
    if (!resp.ok) {
        const primeiroErro = dados && dados.errors ? Object.values(dados.errors)[0][0] : null;
        throw new Error(primeiroErro || (dados && dados.message) || 'Erro inesperado. Tente novamente.');
    }
    return dados;
}

async function acao(fn) {
    try { await fn(); } catch (e) { alert(e.message); }
}

// ---------- sessão ----------
function iniciarApp(usuario) {
    usuarioLogado = usuario.perfil;
    document.getElementById('tela-login').style.display = 'none';
    document.getElementById('app-principal').style.display = 'block';
    document.getElementById('avatar-letra').innerText = usuario.nome.charAt(0).toUpperCase();
    document.getElementById('perfil-logado-texto').innerText = usuario.perfil.toUpperCase();
    configurarMenuPorPerfil();

    const inicio = { vendedor: 'tela-vendedor-visao', empresa: 'tela-empresa-leiloes', operador: 'tela-operador', admin: 'tela-admin' };
    navegarPara(inicio[usuario.perfil]);
}

function voltarParaLogin() {
    token = null;
    usuarioLogado = null;
    sessionStorage.removeItem('irecycle_token');
    document.getElementById('app-principal').style.display = 'none';
    document.getElementById('tela-login').style.display = 'flex';
}

async function logout() {
    try { await api('/logout', { method: 'POST' }); } catch (e) { /* já expirada */ }
    voltarParaLogin();
}

window.onload = async function () {
    // Login
    document.getElementById('form-login').addEventListener('submit', async (e) => {
        e.preventDefault();
        const erro = document.getElementById('login-erro');
        erro.style.display = 'none';
        try {
            const r = await api('/login', {
                method: 'POST',
                body: { email: document.getElementById('login-email').value, senha: document.getElementById('login-senha').value }
            });
            token = r.token;
            sessionStorage.setItem('irecycle_token', token);
            document.getElementById('login-senha').value = '';
            iniciarApp(r.usuario);
        } catch (err) {
            erro.innerText = err.message;
            erro.style.display = 'block';
        }
    });

    // Cadastro
    document.getElementById('cad-perfil').addEventListener('change', (e) => {
        document.getElementById('cad-campos-empresa').style.display = e.target.value === 'empresa' ? 'block' : 'none';
    });
    document.getElementById('form-cadastro').addEventListener('submit', (e) => {
        e.preventDefault();
        acao(async () => {
            const perfil = document.getElementById('cad-perfil').value;
            const r = await api('/cadastro', {
                method: 'POST',
                body: {
                    perfil,
                    nome: document.getElementById('cad-nome').value,
                    email: document.getElementById('cad-email').value,
                    senha: document.getElementById('cad-senha').value,
                    telefone: document.getElementById('cad-telefone').value,
                    razao_social: perfil === 'empresa' ? document.getElementById('cad-razao').value : null,
                    cnpj: perfil === 'empresa' ? document.getElementById('cad-cnpj').value : null
                }
            });
            alert(r.message);
            fecharModal('modal-cadastro');
            e.target.reset();
            document.getElementById('cad-campos-empresa').style.display = 'none';
        });
    });

    // Anunciar material
    document.getElementById('form-anuncio').addEventListener('submit', (e) => {
        e.preventDefault();
        acao(async () => {
            const f = new FormData();
            f.append('material', document.getElementById('material-nome').value);
            f.append('categoria_id', document.getElementById('material-categoria').value);
            f.append('descricao', document.getElementById('material-descricao').value);
            f.append('quantidade', document.getElementById('material-peso').value);
            f.append('lance_minimo', document.getElementById('material-lance').value);
            f.append('data_encerramento', document.getElementById('material-prazo').value);
            const foto = document.getElementById('material-foto').files[0];
            if (foto) f.append('foto', foto);

            await api('/vendedor/anuncios', { method: 'POST', body: f });
            alert('Anúncio publicado com sucesso!');
            fecharModal('modal-anuncio');
            e.target.reset();
            carregarDadosVendedor();
        });
    });

    // Dar lance
    document.getElementById('form-lance').addEventListener('submit', (e) => {
        e.preventDefault();
        acao(async () => {
            const r = await api(`/empresa/leiloes/${leilaoEmLance}/lances`, {
                method: 'POST',
                body: { valor: document.getElementById('lance-valor').value }
            });
            alert(r.message + ' Você será notificado se vencer.');
            fecharModal('modal-lance');
            e.target.reset();
            carregarLeiloes();
        });
    });

    // Restaura a sessão se já havia token (RNF11: a API expira em 30 min de inatividade)
    if (token) {
        try {
            const r = await api('/me');
            iniciarApp(r.usuario);
        } catch (e) { /* cai na tela de login */ }
    }
};

// ---------- navegação ----------
function configurarMenuPorPerfil() {
    const menu = document.getElementById('menu-navegacao');
    const item = (tela, texto) => `<a onclick="navegarPara('${tela}')">${texto}</a>`;
    const menus = {
        vendedor: item('tela-vendedor-visao', 'Visão geral') + item('tela-pontos', 'Pontos de descarte') + item('tela-notificacoes', 'Notificações'),
        empresa: item('tela-empresa-leiloes', 'Leilões Abertos') + item('tela-pontos', 'Pontos de descarte') + item('tela-notificacoes', 'Notificações'),
        admin: item('tela-admin', 'Painel Admin'),
        operador: item('tela-operador', 'Confirmar Entrega')
    };
    menu.innerHTML = menus[usuarioLogado] || '';
}

function navegarPara(telaId) {
    document.querySelectorAll('.conteudo-tela').forEach(t => t.style.display = 'none');
    document.getElementById(telaId).style.display = 'block';

    document.querySelectorAll('#menu-navegacao a').forEach(a => {
        a.classList.toggle('active', (a.getAttribute('onclick') || '').includes(`'${telaId}'`));
    });

    if (telaId === 'tela-vendedor-visao') carregarDadosVendedor();
    if (telaId === 'tela-empresa-leiloes') { carregarLeiloes(); carregarCompras(); }
    if (telaId === 'tela-pontos') carregarPontos();
    if (telaId === 'tela-admin') carregarAdmin();
    if (telaId === 'tela-notificacoes') carregarNotificacoes();
}

// ---------- vendedor ----------
function carregarDadosVendedor() {
    acao(async () => {
        const r = await api('/vendedor/resumo');
        document.getElementById('stat-anuncios').innerText = String(r.estatisticas.anuncios_ativos).padStart(2, '0');
        document.getElementById('stat-lances').innerText = String(r.estatisticas.lances_recebidos).padStart(2, '0');

        document.getElementById('tabela-vendedor-corpo').innerHTML = r.anuncios.map(a => `
            <tr>
                <td>${esc(a.material)}</td>
                <td>${dataHora(a.created_at)}</td>
                <td><span class="badge ${a.status === 'aberto' ? 'badge-ativo' : 'badge-fechado'}">${rotulo(a.status)}</span></td>
                <td>${a.status === 'aberto'
                    ? `<button class="btn-cancel" onclick="cancelarAnuncio(${a.id})">Cancelar</button>`
                    : ''}</td>
            </tr>`).join('') || '<tr><td colspan="4">Você ainda não publicou anúncios.</td></tr>';

        document.getElementById('tabela-vendedor-transacoes').innerHTML = r.transacoes.map(t => `
            <tr>
                <td>${esc(t.material)}</td>
                <td>${esc(t.comprador)}</td>
                <td>${brl(t.valor_final)}</td>
                <td><strong>${esc(t.codigo_descarte)}</strong></td>
                <td><span class="badge ${t.status === 'concluida' ? 'badge-ativo' : 'badge-pendente'}">${rotulo(t.status)}</span></td>
                <td><span class="badge ${t.pagamento === 'pago' ? 'badge-ativo' : 'badge-pendente'}">${rotulo(t.pagamento)}</span></td>
            </tr>`).join('') || '<tr><td colspan="6">Nenhuma negociação fechada ainda.</td></tr>';
    });
}

function cancelarAnuncio(id) {
    if (!confirm('Cancelar este anúncio?')) return;
    acao(async () => {
        await api(`/vendedor/anuncios/${id}/cancelar`, { method: 'POST' });
        carregarDadosVendedor();
    });
}

// ---------- empresa ----------
function carregarLeiloes() {
    acao(async () => {
        const leiloes = await api('/empresa/leiloes');
        leiloesPorId = {};
        leiloes.forEach(l => { leiloesPorId[l.id] = l.material; });
        document.getElementById('grid-leiloes').innerHTML = leiloes.map(l => `
            <div class="card-item">
                <h3>${esc(l.material)}</h3>
                <p><i class="fa-solid fa-tag"></i> ${esc(l.categoria ? l.categoria.nome : '')}</p>
                <p><i class="fa-solid fa-weight-hanging"></i> Quantidade: ${l.quantidade} ${esc(l.unidade)}</p>
                <p><i class="fa-regular fa-clock"></i> Termina em: ${dataHora(l.data_encerramento)}</p>
                <div class="preco">${brl(l.maior_lance ?? l.lance_minimo)}</div>
                <p style="font-size:0.8rem;">${l.maior_lance ? 'Maior lance atual' : 'Lance mínimo (sem lances ainda)'}</p>
                <button class="btn-primary" style="width: 100%; justify-content: center;"
                        onclick="abrirModalLance(${l.id})">Dar Lance</button>
            </div>`).join('') || '<p>Nenhum leilão aberto no momento.</p>';
    });
}

function carregarCompras() {
    acao(async () => {
        const compras = await api('/empresa/compras');
        document.getElementById('tabela-empresa-compras').innerHTML = compras.map(c => `
            <tr>
                <td>${esc(c.material)}</td>
                <td>${brl(c.valor_final)}</td>
                <td><span class="badge ${c.status === 'concluida' ? 'badge-ativo' : 'badge-pendente'}">${rotulo(c.status)}</span></td>
                <td><span class="badge ${c.pagamento === 'pago' ? 'badge-ativo' : 'badge-pendente'}">${rotulo(c.pagamento)}</span></td>
                <td>${c.pagamento === 'pago' ? '' : `<button class="btn-primary" style="padding:5px 10px; font-size:0.8rem;" onclick="pagarCompra(${c.id})">Registrar pagamento</button>`}</td>
            </tr>`).join('') || '<tr><td colspan="5">Nenhuma compra ainda.</td></tr>';
    });
}

function pagarCompra(id) {
    acao(async () => {
        const r = await api(`/empresa/compras/${id}/pagar`, { method: 'POST' });
        alert(r.message);
        carregarCompras();
    });
}

function abrirModalLance(id) {
    leilaoEmLance = id;
    document.getElementById('lance-id').value = id;
    document.getElementById('lance-material-nome').innerText = 'Material: ' + (leiloesPorId[id] || '');
    abrirModal('modal-lance');
}

// ---------- comum ----------
function carregarPontos() {
    acao(async () => {
        const pontos = await api('/pontos');
        document.getElementById('lista-pontos').innerHTML = pontos.map(p => `
            <div class="card-item">
                <h3>${esc(p.nome)}</h3>
                <p><i class="fa-solid fa-location-dot"></i> ${esc(p.endereco)}</p>
                <p><i class="fa-regular fa-clock"></i> ${esc(p.horario_funcionamento)}</p>
                <p><strong>Aceita:</strong> ${esc(p.materiais_aceitos)}</p>
            </div>`).join('') || '<p>Nenhum ponto cadastrado.</p>';
    });
}

function carregarNotificacoes() {
    acao(async () => {
        const lista = await api('/notificacoes');
        document.getElementById('tabela-notificacoes').innerHTML = lista.map(n => `
            <tr><td>${dataHora(n.created_at)}</td><td>${n.lida ? '' : '<strong>• </strong>'}${esc(n.mensagem)}</td></tr>`
        ).join('') || '<tr><td colspan="2">Sem notificações.</td></tr>';
        await api('/notificacoes/lidas', { method: 'POST' });
    });
}

async function carregarCategorias() {
    const cats = await api('/categorias');
    document.getElementById('material-categoria').innerHTML =
        cats.map(c => `<option value="${c.id}">${esc(c.nome)}</option>`).join('');
}

function abrirModal(id) {
    document.getElementById(id).classList.add('active');
    if (id === 'modal-anuncio') acao(carregarCategorias);
}
function fecharModal(id) { document.getElementById(id).classList.remove('active'); }

// ---------- operador ----------
function confirmarEntrega() {
    const campo = document.getElementById('codigo-descarte');
    if (!campo.value.trim()) return alert('Digite um código!');
    acao(async () => {
        const r = await api('/operador/entregas/confirmar', { method: 'POST', body: { codigo: campo.value } });
        alert('✅ ' + r.message);
        campo.value = '';
    });
}

// ---------- admin ----------
function carregarAdmin() {
    acao(async () => {
        const empresas = await api('/admin/empresas');
        document.getElementById('admin-tabela-empresas').innerHTML = empresas.map(e => `
            <tr>
                <td>${esc(e.razao_social)}</td>
                <td>${esc(e.cnpj)}</td>
                <td><span class="badge badge-pendente">${rotulo(e.status_aprovacao)}</span></td>
                <td>
                    <button class="btn-primary" style="padding: 5px 10px; font-size: 0.8rem;" onclick="decidirEmpresa(${e.id}, 'aprovar')">Aprovar</button>
                    <button class="btn-cancel" style="padding: 5px 10px; font-size: 0.8rem;" onclick="decidirEmpresa(${e.id}, 'recusar')">Recusar</button>
                </td>
            </tr>`).join('') || '<tr><td colspan="4">Nenhuma empresa pendente.</td></tr>';
    });
}

function decidirEmpresa(id, decisao) {
    acao(async () => {
        const r = await api(`/admin/empresas/${id}/${decisao}`, { method: 'POST' });
        alert(r.message);
        carregarAdmin();
    });
}

function mudarAbaAdmin(aba, botao) {
    document.querySelectorAll('.admin-conteudo').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(`admin-aba-${aba}`).style.display = 'block';
    if (botao) botao.classList.add('active');
}
