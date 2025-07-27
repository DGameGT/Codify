<?php
require_once "includes/db.php";
require_once "includes/functions.php";

$login_err = "";
if(isLoggedIn()){
    header("location: dashboard.php");
    exit;
}

if($_SERVER["REQUEST_METHOD"] == "POST"){
    if(empty(trim($_POST["username"])) || empty(trim($_POST["password"]))){
        $login_err = "Please enter username and password.";
    } else {
        $sql = "SELECT id, username, password FROM users WHERE username = ?";
        if($stmt = $mysqli->prepare($sql)){
            $stmt->bind_param("s", $_POST["username"]);
            if($stmt->execute()){
                $stmt->store_result();
                if($stmt->num_rows == 1){
                    $stmt->bind_result($id, $username, $hashed_password);
                    if($stmt->fetch()){
                        if(password_verify(trim($_POST["password"]), $hashed_password)){
                            session_start();
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["username"] = $username;                            
                            header("location: dashboard.php");
                        } else{
                            $login_err = "Invalid username or password.";
                        }
                    }
                } else{
                    $login_err = "Invalid username or password.";
                }
            } else{
                echo "Oops! Something went wrong. Please try again later.";
            }
            $stmt->close();
        }
    }
    $mysqli->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - CSC</title>
    <style>
        :root {
            --bg-color: #121212;
            --primary-color: #ffffff;
            --secondary-color: #b3b3b3;
            --accent-color: #1db954;
            --container-bg: #282828;
            --input-bg: #333333;
            --border-color: #444444;
            --danger-color: #e91e63;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
            background-color: var(--bg-color);
            color: var(--primary-color);
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 450px;
            padding: 2rem;
        }
        .form-container {
            background-color: var(--container-bg);
            padding: 2rem;
            border-radius: 8px;
        }
        h1 {
            text-align: center;
            font-size: 2rem;
            margin-bottom: 2rem;
        }
        p {
            color: var(--secondary-color);
            font-size: 0.9rem;
        }
        a {
            color: var(--accent-color);
            text-decoration: none;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        label {
            display: block;
            margin-bottom: 0.5rem;
        }
        .form-control {
            width: 100%;
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            border-radius: 4px;
            padding: 12px;
            color: var(--primary-color);
            font-size: 1rem;
            box-sizing: border-box;
        }
        .btn-primary {
            display: block;
            width: 100%;
            padding: 12px;
            background-color: var(--accent-color);
            color: #ffffff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        .alert-danger {
            background-color: rgba(233, 30, 99, 0.1);
            color: var(--danger-color);
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid var(--danger-color);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <h1>Login to CSC</h1>
            <?php if(!empty($login_err)){ echo '<div class="alert-danger">' . $login_err . '</div>'; } ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control">
                </div>    
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="form-group">
                    <input type="submit" class="btn-primary" value="Login">
                </div>
                <p>Don't have an account? <a href="index.php">Sign up now</a>.</p>
            </form>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            console.log('CSC Loaded - By AlfiDev');
        });
    </script>
</body>
</html>