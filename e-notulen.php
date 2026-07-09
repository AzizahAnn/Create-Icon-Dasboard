<?php
$page_title = "E-Notulen (Voice to Teks)";
$page_icon_img = "icon/e-notulen.png";
include "header.php";
?>

<div class="panel">
    <p class="note" style="margin-bottom:15px;">Fitur ini pakai mikrofon browser (paling stabil di Google Chrome). Klik "Mulai Rekam", lalu bicara — teksnya akan muncul otomatis di bawah.</p>
    <button class="btn-action" id="btnStart"><i class="fa fa-microphone"></i> Mulai Rekam</button>
    <button class="btn-action" id="btnStop" style="background:#ef4444; color:#fff; display:none;"><i class="fa fa-stop"></i> Berhenti</button>
    <p class="note" id="statusText"></p>
</div>

<div class="panel">
    <label>Hasil Notulen</label>
    <textarea id="resultText" placeholder="Hasil ucapan akan muncul di sini..."></textarea>
    <button class="btn-action" id="btnSave"><i class="fa fa-download"></i> Simpan sebagai .txt</button>
</div>

<script>
const statusText = document.getElementById('statusText');
const resultText = document.getElementById('resultText');
const btnStart = document.getElementById('btnStart');
const btnStop = document.getElementById('btnStop');

const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
let recognition;

if (!SpeechRecognition) {
    statusText.textContent = "Browser ini tidak mendukung fitur voice-to-text. Coba pakai Google Chrome.";
    btnStart.disabled = true;
} else {
    recognition = new SpeechRecognition();
    recognition.lang = 'id-ID';
    recognition.continuous = true;
    recognition.interimResults = true;

    recognition.onresult = (event) => {
        let finalText = resultText.value;
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                finalText += event.results[i][0].transcript + " ";
            }
        }
        resultText.value = finalText;
    };

    recognition.onerror = (event) => {
        statusText.textContent = "Error: " + event.error;
    };

    btnStart.addEventListener('click', () => {
        recognition.start();
        statusText.textContent = "Mendengarkan... silakan bicara.";
        btnStart.style.display = "none";
        btnStop.style.display = "inline-block";
    });

    btnStop.addEventListener('click', () => {
        recognition.stop();
        statusText.textContent = "Rekaman dihentikan.";
        btnStart.style.display = "inline-block";
        btnStop.style.display = "none";
    });
}

document.getElementById('btnSave').addEventListener('click', () => {
    const blob = new Blob([resultText.value], { type: 'text/plain' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'notulen_' + Date.now() + '.txt';
    a.click();
});
</script>

<?php include "footer.php"; ?>
