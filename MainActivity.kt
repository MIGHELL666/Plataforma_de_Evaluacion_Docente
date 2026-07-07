import android.content.Intent
import android.net.Uri
import android.os.Bundle
import android.webkit.WebView
import android.webkit.WebViewClient
import android.webkit.CookieManager
import android.webkit.WebSettings
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.widthIn
import androidx.compose.ui.viewinterop.AndroidView
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Button
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import java.net.URLEncoder

private const val BASE_URL = "http://192.168.1.66/UPGOP/"

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent {
            MaterialTheme {
                AppRoot(
                    openUrl = { url ->
                        try {
                            startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url)))
                        } catch (_: Throwable) { }
                    }
                )
            }
        }
    }
}

@Composable
fun RegisterScreen(openUrl: (String) -> Unit, onBack: () -> Unit) {
    val nombre = remember { mutableStateOf("") }
    val matricula = remember { mutableStateOf("") }
    val carrera = remember { mutableStateOf("") }
    val semestre = remember { mutableStateOf("") }
    val grupo = remember { mutableStateOf("") }
    Scaffold { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentAlignment = Alignment.TopCenter
        ) {
            Column(
                modifier = Modifier
                    .padding(16.dp)
                    .widthIn(max = 640.dp),
                horizontalAlignment = Alignment.Start
            ) {
                Text(
                    text = "Crear cuenta de alumno",
                    style = MaterialTheme.typography.headlineSmall,
                    modifier = Modifier.padding(top = 12.dp, bottom = 12.dp)
                )
                OutlinedTextField(
                    value = nombre.value,
                    onValueChange = { nombre.value = it },
                    label = { Text("Nombre completo") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 12.dp)
                )
                OutlinedTextField(
                    value = matricula.value,
                    onValueChange = { matricula.value = it },
                    label = { Text("Matrícula") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 12.dp)
                )
                OutlinedTextField(
                    value = carrera.value,
                    onValueChange = { carrera.value = it },
                    label = { Text("Carrera") },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(bottom = 12.dp)
                )
                Row(
                    modifier = Modifier.fillMaxWidth(),
                    horizontalArrangement = Arrangement.spacedBy(12.dp)
                ) {
                    OutlinedTextField(
                        value = semestre.value,
                        onValueChange = { semestre.value = it },
                        label = { Text("Cuatrimestre (ej. 4to)") },
                        modifier = Modifier.weight(1f)
                    )
                    OutlinedTextField(
                        value = grupo.value,
                        onValueChange = { grupo.value = it },
                        label = { Text("Grupo (ej. A)") },
                        modifier = Modifier.weight(1f)
                    )
                }
                Button(
                    onClick = {
                        val url = BASE_URL + "index.php?seccion=registro_alumno" +
                                "&nombre=" + URLEncoder.encode(nombre.value, "UTF-8") +
                                "&matricula=" + URLEncoder.encode(matricula.value, "UTF-8") +
                                "&carrera=" + URLEncoder.encode(carrera.value, "UTF-8") +
                                "&semestre=" + URLEncoder.encode(semestre.value, "UTF-8") +
                                "&grupo=" + URLEncoder.encode(grupo.value, "UTF-8")
                        openUrl(url)
                    },
                    modifier = Modifier
                        .fillMaxWidth()
                        .padding(top = 12.dp)
                ) {
                    Text("Continuar al registro")
                }
                TextButton(onClick = { onBack() }) {
                    Text("Volver")
                }
            }
        }
    }
}
enum class AppScreen { Login, Register }

@Composable
fun AppRoot(openUrl: (String) -> Unit) {
    val screen = remember { mutableStateOf(AppScreen.Login) }
    when (screen.value) {
        AppScreen.Login -> LoginScreen(openUrl = openUrl, onCreateAccount = { screen.value = AppScreen.Register })
        AppScreen.Register -> RegisterScreen(openUrl = openUrl, onBack = { screen.value = AppScreen.Login })
    }
}

@Composable
fun LoginScreen(openUrl: (String) -> Unit, onCreateAccount: () -> Unit) {
    val errMessage = remember { mutableStateOf("") }
    val okMessage = remember { mutableStateOf("") }
    val matricula = remember { mutableStateOf("") }
    val password = remember { mutableStateOf("") }
    val semestre = remember { mutableStateOf("") }
    val grupo = remember { mutableStateOf("") }
    val showWebView = remember { mutableStateOf(false) }
    val postData = remember { mutableStateOf(ByteArray(0)) }
    val posted = remember { mutableStateOf(false) }
    val cookieHeader = remember { mutableStateOf("") }
    val webLoaded = remember { mutableStateOf(false) }
    

    Scaffold { paddingValues ->
        Box(
            modifier = Modifier
                .fillMaxSize()
                .padding(paddingValues),
            contentAlignment = Alignment.TopCenter
        ) {
            if (showWebView.value) {
                AndroidView(
                    modifier = Modifier.fillMaxSize(),
                    factory = { ctx ->
                        WebView(ctx).apply {
                            settings.javaScriptEnabled = true
                            settings.domStorageEnabled = true
                            settings.mixedContentMode = WebSettings.MIXED_CONTENT_ALWAYS_ALLOW
                            CookieManager.getInstance().setAcceptCookie(true)
                            try { CookieManager.getInstance().setAcceptThirdPartyCookies(this, true) } catch (_: Throwable) {}
                            webViewClient = object : WebViewClient() {
                                override fun onPageFinished(view: WebView?, url: String?) {
                                    super.onPageFinished(view, url)
                                    if (url != null && url.contains("seccion=evaluacion")) {
                                        webLoaded.value = true
                                    }
                                }
                            }
                        }
                    },
                    update = { wv ->
                        val cm = CookieManager.getInstance()
                        if (cookieHeader.value.isNotEmpty()) {
                            cm.setCookie(BASE_URL, cookieHeader.value)
                            try { cm.flush() } catch (_: Throwable) {}
                            if (!webLoaded.value) {
                                wv.loadUrl(BASE_URL + "index.php?seccion=evaluacion")
                                webLoaded.value = true
                            }
                        } else if (!posted.value && postData.value.isNotEmpty()) {
                            wv.postUrl(BASE_URL + "index.php", postData.value)
                            posted.value = true
                        }
                    }
                )
            } else {
                Column(
                modifier = Modifier
                    .padding(16.dp)
                    .widthIn(max = 640.dp),
                horizontalAlignment = Alignment.Start
            ) {
                Text(
                    text = "Accede y califica a tus profesores",
                    style = MaterialTheme.typography.headlineSmall,
                    modifier = Modifier.padding(top = 12.dp, bottom = 12.dp)
                )

                if (errMessage.value.isNotEmpty()) {
                    AlertDialog(
                        onDismissRequest = { errMessage.value = "" },
                        title = { Text("Error") },
                        text = { Text(errMessage.value) },
                        confirmButton = {
                            TextButton(onClick = { errMessage.value = "" }) { Text("Cerrar") }
                        }
                    )
                }
                if (okMessage.value.isNotEmpty()) {
                    AlertDialog(
                        onDismissRequest = { okMessage.value = "" },
                        title = { Text("Éxito") },
                        text = { Text(okMessage.value) },
                        confirmButton = {
                            TextButton(onClick = { okMessage.value = "" }) { Text("Aceptar") }
                        }
                    )
                }

                Column(modifier = Modifier.fillMaxWidth()) {
                    OutlinedTextField(
                        value = matricula.value,
                        onValueChange = { matricula.value = it },
                        label = { Text("Matrícula") },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 12.dp)
                    )
                    OutlinedTextField(
                        value = password.value,
                        onValueChange = { password.value = it },
                        label = { Text("Contraseña") },
                        visualTransformation = PasswordVisualTransformation(),
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(bottom = 12.dp)
                    )
                    Row(
                        modifier = Modifier.fillMaxWidth(),
                        horizontalArrangement = Arrangement.spacedBy(12.dp)
                    ) {
                        OutlinedTextField(
                            value = semestre.value,
                            onValueChange = { semestre.value = it },
                            label = { Text("Cuatrimestre (ej. 4to)") },
                            modifier = Modifier.weight(1f)
                        )
                        OutlinedTextField(
                            value = grupo.value,
                            onValueChange = { grupo.value = it },
                            label = { Text("Grupo (ej. A)") },
                            modifier = Modifier.weight(1f)
                        )
                    }

                    Button(
                        onClick = {
                            if (matricula.value.isBlank() || password.value.isBlank() || semestre.value.isBlank() || grupo.value.isBlank()) {
                                errMessage.value = "Completa todos los campos"
                            } else {
                                val url = BASE_URL + "index.php?seccion=login" +
                                        "&matricula=" + URLEncoder.encode(matricula.value, "UTF-8") +
                                        "&semestre=" + URLEncoder.encode(semestre.value, "UTF-8") +
                                        "&grupo=" + URLEncoder.encode(grupo.value, "UTF-8")
                                openUrl(url)
                            }
                        },
                        modifier = Modifier
                            .fillMaxWidth()
                            .padding(top = 12.dp)
                    ) {
                        Text("Acceder y evaluar")
                    }
                }

                TextButton(onClick = { onCreateAccount() }) {
                    Text("Crear cuenta")
                }

                Box(modifier = Modifier.height(8.dp))
            }
        }
    }
}
