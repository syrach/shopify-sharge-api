<!doctype html>
<html>
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    @vite('resources/css/app.css')
</head>
<body>

<div class="container mx-auto py-8 px-4">
    <h1 class="text-2xl font-bold mb-6">Adres Hatalı Siparişler </h1>

    <div class="border border-gray-300 rounded-md overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
            <tr>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Sipariş ID
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Shopify ID
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Müşteri
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Telefon Numarası
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Durum
                </th>
                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                    Hata Mesajı
                </th>
                <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                    İşlem
                </th>
            </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            @foreach($orders as $order)
                @if($order->sync_status === 'address-failed')
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap font-medium">
                        {{ $order->id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap font-medium">
                        {{ $order->order_id }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ $order->full_name }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        {{ $order->mobile_phone }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        @if($order->sync_status === 'address-failed')
                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset">
                                Adres Hatası
                            </span>
                        @elseif($order->sync_status === 'waiting')
                            <span class="inline-flex items-center rounded-md bg-yellow-50 px-2 py-1 text-xs font-medium text-yellow-800 ring-1 ring-yellow-600/20 ring-inset">
                                Beklemede
                            </span>
                        @elseif($order->sync_status === 'completed')
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-medium text-green-700 ring-1 ring-green-600/20 ring-inset">
                                Tamamlandı
                            </span>
                        @elseif($order->sync_status === 'failed')
                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-medium text-red-700 ring-1 ring-red-600/10 ring-inset">
                                Başarısız
                            </span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-gray-50 px-2 py-1 text-xs font-medium text-gray-600 ring-1 ring-gray-500/10 ring-inset">
                                {{ $order->sync_status }}
                            </span>
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-sm text-red-600">{{ $order->sync_error }}</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <button
                            type="button"
                            class="inline-flex items-center px-3 py-1.5 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                            onclick="openModal('{{ $order->id }}')"
                        >
                            Düzelt
                        </button>
                    </td>
                </tr>
                @endif
            @endforeach
            </tbody>
        </table>
    </div>

    <!-- Error Modal -->
    <div id="errorModal" class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center hidden z-50">
        <div class="bg-white rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-2xl sm:w-full">
            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                <div class="sm:flex sm:items-start">
                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Sipariş Adresi Düzeltme
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500" id="modal-description">
                                <span id="order-id"></span> numaralı siparişin adres bilgilerini düzeltin.
                            </p>
                        </div>

                        <div class="mt-4">
                            <!-- Tab Navigation -->
                            <div class="border-b border-gray-200">
                                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                    <button onclick="switchTab('invoice')" class="tab-button border-indigo-500 text-indigo-600 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" id="invoice-tab">
                                        Fatura Adresi
                                    </button>
                                    <button onclick="switchTab('shipping')" class="tab-button border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm" id="shipping-tab">
                                        Kargo Adresi
                                    </button>
                                </nav>
                            </div>

                            <!-- Tab Content -->
                            <form id="orderForm" action="" method="POST" class="mt-4">
                                @csrf
                                @method('PUT')

                                <!-- Fatura Adresi Tab -->
                                <div id="invoice-tab-content" class="tab-content">
                                    <div class="space-y-4">
                                        <div class="grid mb-6 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="invoice_city" class="block mb-2 text-sm font-medium text-gray-700">
                                                    İl
                                                </label>
                                                <input type="text" name="invoice_city" id="invoice_city" class="flex h-10 w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required />
                                            </div>
                                            <div>
                                                <label for="invoice_district" class="block mb-2 text-sm font-medium text-gray-700">
                                                    İlçe
                                                </label>
                                                <input type="text" name="invoice_district" id="invoice_district" class="flex h-10 w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required />
                                            </div>
                                        </div>
                                        <div class="grid mb-6 md:grid-cols-2">
                                            <label for="invoice_address" class="block mb-2 text-sm font-medium text-gray-700">
                                                Adres
                                            </label>
                                            <div class="col-span-3">
                                                <textarea name="invoice_address" id="invoice_address" rows="2" class="block w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Kargo Adresi Tab -->
                                <div id="shipping-tab-content" class="tab-content hidden">
                                    <div class="space-y-4">
                                        <div class="grid mb-6 md:grid-cols-2 gap-4">
                                            <div>
                                                <label for="ship_city" class="block mb-2 text-sm font-medium text-gray-700">
                                                    İl
                                                </label>
                                                <input type="text" name="ship_city" id="ship_city" class="flex h-10 w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required />
                                            </div>
                                            <div>
                                                <label for="ship_district" class="block mb-2 text-sm font-medium text-gray-700">
                                                    İlçe
                                                </label>
                                                <input type="text" name="ship_district" id="ship_district" class="flex h-10 w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required />
                                            </div>
                                        </div>
                                        <div class="grid mb-6 md:grid-cols-2">
                                            <label for="ship_address" class="block mb-2 text-sm font-medium text-gray-700">
                                                Adres
                                            </label>
                                            <div class="col-span-3">
                                                <textarea name="ship_address" id="ship_address" rows="2" class="block w-full rounded-md border border-gray-200 border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 col-span-3" required></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                <button type="button" onclick="submitForm()" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                    Kaydet
                </button>
                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                    İptal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    // Store order data for use in the modal
    const orders = @json($orders);

    function openModal(orderId) {
        const order = orders.find(o => o.id == orderId);
        if (!order) return;

        // Set form action
        document.getElementById('orderForm').action = `/orders/update/${orderId}`;

        // Fill modal with order data
        document.getElementById('order-id').textContent = order.id;

        // Fatura adresi bilgileri
        document.getElementById('invoice_city').value = order.invoice_city;
        document.getElementById('invoice_district').value = order.invoice_district;
        document.getElementById('invoice_address').value = order.invoice_address;

        // Kargo adresi bilgileri
        document.getElementById('ship_city').value = order.ship_city;
        document.getElementById('ship_district').value = order.ship_district;
        document.getElementById('ship_address').value = order.ship_address;

        // Show modal
        document.getElementById('errorModal').classList.remove('hidden');
    }

    function closeModal() {
        document.getElementById('errorModal').classList.add('hidden');
    }

    function submitForm() {
        // Tüm zorunlu alanları kontrol et
        const requiredFields = document.querySelectorAll('[required]');
        let isValid = true;

        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('border-red-500');
            } else {
                field.classList.remove('border-red-500');
            }
        });

        if (!isValid) {
            alert('Lütfen tüm zorunlu alanları doldurun.');
            return;
        }

        document.getElementById('orderForm').submit();
    }

    function switchTab(tabName) {
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.add('hidden');
        });

        // Remove active state from all tabs
        document.querySelectorAll('.tab-button').forEach(button => {
            button.classList.remove('border-indigo-500', 'text-indigo-600');
            button.classList.add('border-transparent', 'text-gray-500');
        });

        // Show selected tab content
        document.getElementById(`${tabName}-tab-content`).classList.remove('hidden');

        // Add active state to selected tab
        document.getElementById(`${tabName}-tab`).classList.remove('border-transparent', 'text-gray-500');
        document.getElementById(`${tabName}-tab`).classList.add('border-indigo-500', 'text-indigo-600');
    }

    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('errorModal');
        if (event.target === modal) {
            closeModal();
        }
    });
</script>
</body>
</html>
