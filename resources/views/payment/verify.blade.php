<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد الدفع</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <!-- Header -->
            <div class="bg-red-600 text-white px-6 py-8 text-center">
                <div class="text-5xl mb-3">💳</div>
                <h1 class="text-xl font-bold">بوابة الدفع</h1>
                <p class="text-red-100 mt-1 text-sm">Vodafone Cash</p>
            </div>

            <!-- Transfer Info -->
            <div class="bg-red-50 px-6 py-4 text-center border-b border-red-100">
                <p class="text-gray-600 text-sm mb-1">قم بالتحويل إلى الرقم</p>
                <p class="text-2xl font-bold text-red-600 tracking-wider" dir="ltr">{{ $paymentPhone }}</p>
            </div>

            <!-- Form -->
            <div class="p-6">
                <form id="verifyForm" class="space-y-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف المرسل منه</label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            required
                            placeholder="01XXXXXXXXX"
                            pattern="01[012][0-9]{8}"
                            maxlength="11"
                            minlength="11"
                            title="رقم الهاتف لازم يبدأ بـ 010 أو 011 أو 012 ويكون 11 رقم"
                            class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:ring-2 focus:ring-red-500 focus:border-red-500 outline-none text-lg"
                            dir="ltr"
                        >
                    </div>

                    <button
                        type="submit"
                        id="submitBtn"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-4 rounded-xl transition-colors duration-200 text-lg cursor-pointer"
                    >
                        تأكيد الدفع
                    </button>
                </form>

                <!-- Result Message -->
                <div id="result" class="mt-4 hidden">
                    <div id="resultContent" class="p-4 rounded-xl text-center font-medium"></div>
                </div>
            </div>
        </div>

        <p class="text-center text-gray-400 text-xs mt-4">SMS Guard Payment Gateway</p>
    </div>

    <script>
        const form = document.getElementById('verifyForm');
        const submitBtn = document.getElementById('submitBtn');
        const resultDiv = document.getElementById('result');
        const resultContent = document.getElementById('resultContent');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const phone = document.getElementById('phone').value;

            if (!/^01[012]\d{8}$/.test(phone)) {
                resultDiv.classList.remove('hidden');
                resultContent.className = 'p-4 rounded-xl text-center font-medium bg-red-50 text-red-700 border border-red-200';
                resultContent.innerHTML = '❌ رقم الهاتف لازم يبدأ بـ 010 أو 011 أو 012 ويكون 11 رقم';
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'جاري التحقق...';
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            resultDiv.classList.add('hidden');

            try {
                const response = await fetch('/api/verify-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ phone }),
                });

                const data = await response.json();

                resultDiv.classList.remove('hidden');

                if (data.success) {
                    resultContent.className = 'p-4 rounded-xl text-center font-medium bg-green-50 text-green-700 border border-green-200';
                    resultContent.innerHTML = '✅ ' + data.message;
                    submitBtn.style.display = 'none';
                } else {
                    resultContent.className = 'p-4 rounded-xl text-center font-medium bg-red-50 text-red-700 border border-red-200';
                    resultContent.innerHTML = '❌ ' + data.message;
                }
            } catch (error) {
                resultDiv.classList.remove('hidden');
                resultContent.className = 'p-4 rounded-xl text-center font-medium bg-yellow-50 text-yellow-700 border border-yellow-200';
                resultContent.innerHTML = '⚠️ حدث خطأ، حاول مرة أخرى';
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'تأكيد الدفع';
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            }
        });
    </script>
</body>
</html>
