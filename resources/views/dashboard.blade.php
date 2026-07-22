@extends('layouts.app')
@section('title', 'Painel - Carteira Financeira')
@section('content')
<div id="app">
    <div class="navbar">
        <h1>💰 Carteira Financeira</h1>
        <nav>
            <button class="btn-logout" @click="logout">Sair</button>
        </nav>
    </div>
    <div class="container">
        <!-- Alerts -->
        <div v-if="alert.message" :class="['alert', alert.type === 'error' ? 'alert-error' : 'alert-success']">
            @{{ alert.message }}
        </div>

        <!-- Balance Card -->
        <div class="card">
            <h2>Saldo Atual</h2>
            <div class="balance-display" :class="{ negative: parseFloat(balance) < 0 }">
                R$ @{{ balance }}
            </div>
        </div>

        <!-- Actions -->
        <div class="grid-2">
            <!-- Deposit -->
            <div class="card">
                <h2>Depositar</h2>
                <form @submit.prevent="deposit">
                    <div class="form-group">
                        <label>Valor (R$)</label>
                        <input type="number" v-model="depositAmount" step="0.01" min="0.01" required>
                    </div>
                    <button type="submit" class="btn btn-success" :disabled="loading">Depositar</button>
                </form>
            </div>

            <!-- Transfer -->
            <div class="card">
                <h2>Transferir</h2>
                <form @submit.prevent="transfer">
                    <div class="form-group">
                        <label>Destinatário</label>
                        <select v-model="transferForm.receiver_id" required>
                            <option value="" disabled>Selecione um destinatário</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">
                                @{{ user.name }} (@{{ user.email }})
                            </option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Valor (R$)</label>
                        <input type="number" v-model="transferForm.amount" step="0.01" min="0.01" required>
                        <small v-if="transferExceedsBalance" class="text-danger">
                            Valor superior ao saldo disponível
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary"
                        :disabled="loading || transferExceedsBalance || !transferForm.receiver_id || !transferForm.amount">
                        Transferir
                    </button>
                </form>
            </div>
        </div>

        <!-- Statement -->
        <div class="card">
            <h2>Extrato</h2>
            <table v-if="transactions.length">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tx in transactions" :key="tx.uuid">
                        <td>@{{ formatDate(tx.date) }}</td>
                        <td><span :class="'badge badge-' + tx.type">@{{ typeLabel(tx.type) }}</span></td>
                        <td>R$ @{{ tx.amount }}</td>
                        <td>@{{ tx.is_reversed ? '↩️ Revertido' : '✓' }}</td>
                        <td>
                            <button v-if="!tx.is_reversed && tx.type !== 'reversal' && tx.type !== 'transfer_received'"
                                class="btn btn-danger btn-sm" @click="reverse(tx)">
                                Reverter
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else style="color:#888; text-align:center; padding:1rem;">Nenhuma transação encontrada.</p>
        </div>
    </div>
</div>
<style>
    select { width: 100%; padding: .6rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; background: #fff; }
    select:focus { outline: none; border-color: #3498db; }
    .text-danger { color: #e74c3c; font-size: .8rem; margin-top: .3rem; display: block; }
    .btn:disabled { opacity: .5; cursor: not-allowed; }
</style>
<script>
const { createApp, ref, computed, onMounted, onUnmounted } = Vue;
createApp({
    setup() {
        const balance = ref('0.00');
        const transactions = ref([]);
        const users = ref([]);
        const depositAmount = ref('');
        const transferForm = ref({ receiver_id: '', amount: '' });
        const loading = ref(false);
        const alert = ref({ message: '', type: '' });
        let balanceInterval = null;

        const transferExceedsBalance = computed(() => {
            const amount = parseFloat(transferForm.value.amount);
            if (!amount || amount <= 0) return false;
            return amount > parseFloat(balance.value);
        });

        function getToken() {
            return localStorage.getItem('jwt_token');
        }

        function headers() {
            return {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + getToken()
            };
        }

        function showAlert(message, type = 'success') {
            alert.value = { message, type };
            setTimeout(() => { alert.value = { message: '', type: '' }; }, 4000);
        }

        async function fetchBalance() {
            try {
                const res = await fetch('/api/wallet/balance', { headers: headers() });
                if (res.status === 401) { window.location.href = '/login'; return; }
                const data = await res.json();
                balance.value = data.balance;
            } catch (e) { /* silent */ }
        }

        async function fetchStatement() {
            const res = await fetch('/api/wallet/statement', { headers: headers() });
            if (res.status === 401) { window.location.href = '/login'; return; }
            transactions.value = await res.json();
        }

        async function fetchUsers() {
            try {
                const res = await fetch('/api/users', { headers: headers() });
                if (res.ok) {
                    users.value = await res.json();
                }
            } catch (e) { /* silent */ }
        }

        async function deposit() {
            loading.value = true;
            try {
                const res = await fetch('/api/wallet/deposit', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({ amount: parseFloat(depositAmount.value) })
                });
                const data = await res.json();
                if (!res.ok) {
                    showAlert(data.errors?.amount?.[0] || 'Erro no depósito', 'error');
                    return;
                }
                balance.value = data.balance;
                depositAmount.value = '';
                showAlert('Depósito realizado com sucesso!');
                fetchStatement();
            } catch (e) {
                showAlert('Erro de conexão', 'error');
            } finally {
                loading.value = false;
            }
        }

        async function transfer() {
            loading.value = true;
            try {
                const res = await fetch('/api/wallet/transfer', {
                    method: 'POST',
                    headers: headers(),
                    body: JSON.stringify({
                        receiver_id: parseInt(transferForm.value.receiver_id),
                        amount: parseFloat(transferForm.value.amount)
                    })
                });
                const data = await res.json();
                if (!res.ok) {
                    const msg = data.errors?.amount?.[0] || data.errors?.receiver?.[0] || data.errors?.receiver_id?.[0] || 'Erro na transferência';
                    showAlert(msg, 'error');
                    return;
                }
                balance.value = data.balance;
                transferForm.value = { receiver_id: '', amount: '' };
                showAlert('Transferência realizada com sucesso!');
                fetchStatement();
            } catch (e) {
                showAlert('Erro de conexão', 'error');
            } finally {
                loading.value = false;
            }
        }

        async function reverse(tx) {
            if (!confirm('Deseja reverter esta transação?')) return;
            loading.value = true;
            try {
                const res = await fetch('/api/wallet/reverse/' + tx.id, {
                    method: 'POST',
                    headers: headers()
                });
                const data = await res.json();
                if (!res.ok) {
                    showAlert(data.errors?.transaction?.[0] || 'Erro na reversão', 'error');
                    return;
                }
                balance.value = data.balance;
                showAlert('Transação revertida com sucesso!');
                fetchStatement();
            } catch (e) {
                showAlert('Erro de conexão', 'error');
            } finally {
                loading.value = false;
            }
        }

        function logout() {
            fetch('/api/logout', { method: 'POST', headers: headers() });
            localStorage.removeItem('jwt_token');
            window.location.href = '/login';
        }

        function formatDate(iso) {
            return new Date(iso).toLocaleString('pt-BR');
        }

        function typeLabel(type) {
            const labels = {
                deposit: 'Depósito',
                transfer_sent: 'Enviada',
                transfer_received: 'Recebida',
                reversal: 'Reversão'
            };
            return labels[type] || type;
        }

        onMounted(() => {
            if (!getToken()) { window.location.href = '/login'; return; }
            fetchBalance();
            fetchStatement();
            fetchUsers();
            // Atualiza saldo a cada 5 segundos
            balanceInterval = setInterval(fetchBalance, 5000);
        });

        onUnmounted(() => {
            if (balanceInterval) clearInterval(balanceInterval);
        });

        return { balance, transactions, users, depositAmount, transferForm,
                 loading, alert, transferExceedsBalance,
                 deposit, transfer, reverse, logout, formatDate, typeLabel };
    }
}).mount('#app');
</script>
@endsection
