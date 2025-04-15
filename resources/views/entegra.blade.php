<html>

<body>
<form method="post" enctype="multipart/form-data" action="{{ route('manuel-post') }}">
    @csrf
    <input name="id" type="text" placeholder="ID Giriniz">
    <button type="submit">Gönder</button>
</form>
</body>
</html>
