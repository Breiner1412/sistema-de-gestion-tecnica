{{-- Al cerrar sesión se llega aquí sin sesión: se borran las copias de las
     páginas de campo que guardó el service worker, para que quien use después
     este teléfono no las vea sin señal. La cola de cierres (IndexedDB) NO se
     toca: ahí puede haber firmas y fotos de otra cuenta esperando a su dueño. --}}
@guest
    <script>
        if ('caches' in window) {
            caches.keys()
                .then((nombres) => Promise.all(
                    nombres.filter((n) => n.startsWith('sgt-campo')).map((n) => caches.delete(n)),
                ))
                .catch(() => {});
        }
    </script>
@endguest
