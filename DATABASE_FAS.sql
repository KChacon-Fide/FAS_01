/*FAS DATABASE*/

CREATE DATABASE FAS;

USE Fas;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(80) NOT NULL,
  correo VARCHAR(120) NOT NULL UNIQUE,
  clave VARCHAR(255) NOT NULL,
  rol ENUM('admin','miembro') NOT NULL DEFAULT 'miembro',
  estado TINYINT(1) NOT NULL DEFAULT 1,
  fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO usuarios (nombre, correo, clave, rol, estado)
VALUES (
  'Administrador FAS',
  'admin@fas.com',
  '$2y$10$zgBvz7Uk/BHfRY2m/dCM9uErsstx3edF7leOBKlEghOZYXmVx8psK',
  'admin',
  1
);

USE fas;

CREATE TABLE gastos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  detalle VARCHAR(120) NOT NULL,
  monto DECIMAL(12,2) NOT NULL,
  fecha DATE NOT NULL,
  metodo_pago ENUM('Efectivo','Tarjeta','SINPE','Transferencia','Otro') NOT NULL DEFAULT 'Efectivo',
  notas VARCHAR(255) NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (usuario_id),
  CONSTRAINT fk_gastos_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gastos
  ADD COLUMN persona_id INT NULL AFTER usuario_id,
  ADD INDEX (persona_id);



-- 1) Personas de la familia (vinculadas al usuario logueado)
CREATE TABLE personas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(usuario_id),
  CONSTRAINT fk_personas_usuario
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE gastos
  ADD CONSTRAINT fk_gastos_persona
  FOREIGN KEY (persona_id) REFERENCES personas(id)
  ON DELETE SET NULL
  ON UPDATE CASCADE;


-- 2) Balance por persona (total = efectivo + tarjeta, y sobres aparte)
CREATE TABLE persona_balance (
  persona_id INT PRIMARY KEY,
  efectivo DECIMAL(12,2) NOT NULL DEFAULT 0,
  tarjeta  DECIMAL(12,2) NOT NULL DEFAULT 0,
  sobres   DECIMAL(12,2) NOT NULL DEFAULT 0,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_balance_persona
    FOREIGN KEY (persona_id) REFERENCES personas(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Movimientos (ingresos / transferencias / sobres)
CREATE TABLE movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('INGRESO','TRANSFERENCIA','SOBRE_IN','SOBRE_OUT') NOT NULL,
  persona_origen_id INT NULL,
  persona_destino_id INT NULL,
  metodo ENUM('Efectivo','Tarjeta') NULL,
  monto DECIMAL(12,2) NOT NULL,
  detalle VARCHAR(160) NOT NULL,
  fecha DATE NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX(usuario_id),
  INDEX(persona_origen_id),
  INDEX(persona_destino_id),
  CONSTRAINT fk_mov_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_mov_origen FOREIGN KEY (persona_origen_id) REFERENCES personas(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_mov_destino FOREIGN KEY (persona_destino_id) REFERENCES personas(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;






/* 👉 Correo: admin@fas.com
   👉 Contraseña: admin123 */
   
   
select * from usuarios;
select * from gastos;
select * from personas;
select * from persona_balance;
select * from movimientos;