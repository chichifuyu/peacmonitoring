<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dropdown Test</title>
  <style>
    .dropdown-menu {
      display: none;
    }
    .dropdown-menu.show {
      display: block;
    }
  </style>
</head>
<body>

  <button onclick="toggleDropdown('dropdownTest')">Toggle Dropdown</button>
  <ul id="dropdownTest" class="dropdown-menu">
    <li>Dashboard 1</li>
    <li>Dashboard 2</li>
  </ul>

  <script>
    function toggleDropdown(id) {
      var dropdownMenu = document.getElementById(id);
      dropdownMenu.classList.toggle('show');
    }
  </script>

</body>
</html>
