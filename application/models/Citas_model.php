<?php
defined('BASEPATH') OR exit('No direct script access Allowed');

class Citas_model extends CI_model {

	public function __construct() {
		parent::__construct();
		$this->load->database();
	}

	public function seguridad($user,$pass) {
		$query = $this->db->query("
			SELECT * FROM usuarios WHERE usuario = '$user' AND password = '$pass' AND estado = 1
		");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function buscar_pacientes($text) {
		$sql="SELECT idexpediente, nombre_completo FROM expedientes WHERE estado=0 ";
		$bus=explode(" ", $text);
		foreach ($bus as $key => $value) {
			$sql.="AND concat_ws(' ',nombres,apellidos) LIKE '%".$value."%'";
		}
		$sql.="ORDER BY idexpediente DESC LIMIT 0,500";
		$pacientes = $this->db->query($sql)->result_array();
		return $pacientes;
	}

	public function sucursales() {
		$sucursales = $this->db->query("
			SELECT idsucursal, sucursal FROM sucursales WHERE status = 0
		")->result_array();
		return $sucursales;
	}

	public function especialidades($idsucursal) {
		$especialidades = $this->db->query("
			SELECT
				es.id_especialidad, es.especialidad
			FROM
				medicos me
				INNER JOIN especialidades es ON es.id_especialidad = me.id_especialidad
			WHERE
				me.idsucursal = $idsucursal
				AND me.estado = 0
				AND me.app = 1
				AND es.estado = 1
				AND es.app = 1
			GROUP BY
				id_especialidad
		")->result_array();
		return $especialidades;
	}

	public function medicos($idsucursal,$especialidad) {
		$medicos = $this->db->query("
			SELECT
				idmedico, CONCAT(me.nombres, ' ', me.apellidos) AS 'nombre_medico'
			FROM
				medicos me
				INNER JOIN especialidades es ON es.id_especialidad = me.id_especialidad
			WHERE
			  	me.idsucursal = $idsucursal
			  	AND es.id_especialidad = $especialidad
				AND me.estado = 0
				AND me.app = 1
				AND es.estado = 1
				AND es.app = 1
		")->result_array();
		return $medicos;
	}

	public function procedimientos($idmedico) {
		$procedimientos = $this->db->query("
			SELECT
				procedimientos_medicos_especialidades.id_procedimiento_medico_especialidad, procedimientos_medicos_especialidades.periodo_minimo, TIME_FORMAT(procedimientos_medicos_especialidades.periodo_minimo, '%H:%i') AS 'periodo', procedimientos_medicos.nombre_procedimiento
			FROM
				medicos
				INNER JOIN procedimientos_medicos_especialidades ON procedimientos_medicos_especialidades.id_especialidad = medicos.id_especialidad
				INNER JOIN procedimientos_medicos ON procedimientos_medicos.id_procedimiento_medico = procedimientos_medicos_especialidades.id_procedimiento_medico
			WHERE
				medicos.idmedico = $idmedico AND procedimientos_medicos_especialidades.estado = 1;
		")->result_array();
		return $procedimientos;
	}

	public function horas_desde($idProcedimiento,$fecha,$idMedico){
		$fecha   =  date('Y-m-d'  , strtotime($fecha)   );  //Convercion de la fecha
		$horasDisponibles =  array();
		// $horario  =    $this->obtenerHorarioMedicoDesde(  $idMedico ,  $fecha  , 0 )  ;//Horario de atencion de medico
		$horasHorarioMedico = $this->obtenerHoraInicioFinHorario($idMedico , $fecha); //Obtiene hora de inicio y hora fin de horario

		//Si se ha establecido un horario para el medico
		if($horasHorarioMedico[0]['horaInicio']!=NULL && $horasHorarioMedico[0]['horaFin'] != NULL   ){

			// Editar el valor para cambiar el tamaño de los intervalos
			$incrementosHoras =  5; //Incrementos en minutos de cada hora
			$periodoMinimoProcedimiento =  $this->obtenerPeriodoProcedimiento($idProcedimiento);  //Periodo minimo establecido para el procedimiento
			$horaInicioHorario = $horasHorarioMedico[0]['horaInicio'].":00:00";
			$horaFinalizacionHorario =  $horasHorarioMedico[0]['horaFin'].":00:00";   //VALIDAR POR FAVOR QUE EL MEDICO TENGA HORARIO DISPONIBLE
			$horaFinalizacionHorario = (new DateTime($horaFinalizacionHorario))->modify("+1 hours")->format("H:i:s");

			$horario = $this->obtenerConjuntosHoras( $horaInicioHorario , $horaFinalizacionHorario  , $idMedico ,  $fecha  );

			foreach ($horario as $key => $hora) {
				for(  $i = 0 ;  $i <60 ;  ($i+= $incrementosHoras)    ){ //Iteraciones por hora
					$horaIteracion   =  $hora['hora'].":".(   ($i < 10 ) ? "0".$i : $i  ).":00";
					$sumaHoras = $this->sumarHoras(  $horaIteracion  ,  $periodoMinimoProcedimiento  );
					//Array ,  key  , value
					$he = $this->buscarEnArray($horario ,  'hora' , $hora['hora']);    //Busca a que conjunto pertenece la hora  desde
					$hpc = $this->buscarEnArray($horario ,  'hora' , date("H" , strtotime($sumaHoras))   );    //Busca a que conjunto pertenece la hora hasta

					if($sumaHoras <= $horaFinalizacionHorario){
						if($he[0]['conjunto'] ==  $hpc[0]['conjunto']){  //Los conjuntos de horas deben de ser continuos
							$disponibilidad=$this->verificarDisponibilidadHorario2(  0 ,  $horaIteracion , $fecha , $idMedico , $periodoMinimoProcedimiento , 1);
							if($disponibilidad){
								$horasDisponibles[] =  $horaIteracion;
							}
						}
					}else{
						break;
					}
				}
			}
		}
		return $horasDisponibles;
	}

	public function obtenerHoraInicioFinHorario($idMedico,$fecha){
		$sqlHorarioMedico = "SELECT MIN(hora) AS horaInicio ,MAX(hora) AS  horaFin  FROM medicos_horarios
        INNER JOIN horas
        ON horas.Idhora  =  medicos_horarios.idhorario
        WHERE medicos_horarios.fecha = '$fecha'
        AND medicos_horarios.estado = 0 
        AND medicos_horarios.idmedico = $idMedico        
        ORDER BY horas.Idhora ASC;";
		return $this->db->query($sqlHorarioMedico)->result_array();
	}

	public function obtenerPeriodoProcedimiento($idPeriodoProcedimientoMedico){
		$sqlPeriodoProcedimiento  ="SELECT  *  FROM  procedimientos_medicos_especialidades  WHERE id_procedimiento_medico_especialidad = $idPeriodoProcedimientoMedico;";
		$periodoProcedimiento  = $this->db->query($sqlPeriodoProcedimiento)->result_array();
		return $periodoProcedimiento[0]['periodo_minimo'];
	}

	public function obtenerConjuntosHoras( $horaInicialHorario,$horaFinalHorario,$idMedico,$fecha){
		$horaInicialHorario = date("H" , strtotime($horaInicialHorario));
		$horaFinalHorario= date("H" , strtotime($horaFinalHorario));
		$sqlConjuntosHoras = "SELECT horas.hora,
        medicos_horarios.idmedico , 
        @conjunto AS conjunto , 
        (CASE WHEN (idmedico IS NULL) THEN  (@conjunto := @conjunto + 1 ) ELSE NULL END   ) AS  cambio        
        FROM
            horas
        LEFT JOIN medicos_horarios ON (
            medicos_horarios.idhorario = horas.idhora
            AND medicos_horarios.fecha = '$fecha'
            AND medicos_horarios.estado = 0
            AND medicos_horarios.idmedico = $idMedico
        )  WHERE  horas.hora BETWEEN $horaInicialHorario  AND $horaFinalHorario  ORDER BY hora ;";
		$this->db->query("SET @conjunto = 1;");
		$horas = $this->db->query($sqlConjuntosHoras)->result_array();
		return $horas;
	}

	public function sumarHoras($horaA , $horaB){
		$timeB = strtotime($horaB);
		$horasSuma = date("H" ,  $timeB  );
		$minutosSuma = date("i" ,   $timeB  );
		$segundosSuma =  date("s" ,  $timeB  );

		$horaA = (new DateTime($horaA))->modify("+$horasSuma hours");
		$horaA = $horaA->modify("+$minutosSuma minute");
		$horaA = $horaA->modify("+$segundosSuma second");

		return $horaA->format("H:i:s");
	}

	public function buscarEnArray($array,$key,$value){
		$results = array();
		if (is_array($array)) {
			if (isset($array[$key]) && $array[$key] == $value) {
				$results[] = $array;
			}
			foreach ($array as $subarray) {
				$results = array_merge($results, $this->buscarEnArray($subarray, $key, $value));
			}
		}
		return $results;
	}

	public function verificarDisponibilidadHorario2($idexpediente,$horaInicio,$fecha,$idMedico,$periodoMinimoProcedimiento,$tipoFiltro){
		$horaFinProcedimiento =  $this->sumarHoras(  $horaInicio , $periodoMinimoProcedimiento   );
		//CONDICION PARA RESERVAS CITAS
		$reservaCitas = "  '$horaInicio'   BETWEEN   ADDTIME(  TIME(fechahora) , '00:01:00'     )  AND   SUBTIME( TIME(fechahorafin)  , '00:01:00'    )  OR 
                                      '$horaFinProcedimiento'   BETWEEN    ADDTIME(  TIME(fechahora) , '00:01:00'     )  AND   SUBTIME( TIME(fechahorafin)  , '00:01:00'    )   OR 
                                      ADDTIME(  TIME(fechahora) , '00:01:00'     )   BETWEEN   '$horaInicio'  AND '$horaFinProcedimiento'  OR 
                                      SUBTIME( TIME(fechahorafin)  , '00:01:00'    )  BETWEEN   '$horaInicio'  AND '$horaFinProcedimiento'  ";

		//SI EL MEDICO TIENE EL HORARIO DISPONIBLE
		$queryHorarioMedico =  $this->db->query("SELECT  COUNT(*) AS disponibilidad_medico FROM medicos_horarios 
        INNER JOIN horas 
        ON horas.idhora  =  medicos_horarios.idhorario
        WHERE medicos_horarios.fecha = '$fecha'
        AND medicos_horarios.idmedico = $idMedico
        AND medicos_horarios.estado = 0            
        AND '$horaFinProcedimiento'   BETWEEN CONCAT(hora ,':00:00') AND ADDTIME(CONCAT(hora ,':00:00') , '01:00:00')       
        ORDER BY idhora;")->result_array();

		$disponibilidad_medico =  ($queryHorarioMedico[0]['disponibilidad_medico']  >= 1) ?  true :  false;

		if($disponibilidad_medico){   //SI EL HORARIO ESTA DISPONIBLE PARA EL MEDICO

			//SI LA RESERVA EXISTE
			$query2 = $this->db->query("SELECT COUNT(*) AS num FROM reserva_citas
            LEFT JOIN reservas_fichas
            ON reservas_fichas.idreservacita  =  reserva_citas.idreservacita            
             WHERE reserva_citas.idmedico='".$idMedico."' AND 
             reserva_citas.fecha='".$fecha."' AND 
             ( 
                reserva_citas.status=1  OR 
                reserva_citas.status=2  OR 
                reserva_citas.status=3  OR 
                ( reserva_citas.status=5  AND reservas_fichas.tipo_seguimiento  = 3  )
            )
            AND  (   $reservaCitas  )  ")->result_array();
			$horario_existe = $query2[0]['num'];
			if(
				//$res_existe > 0 ||
				$horario_existe  >0  ){
				return false;
			}else{
				return true;
			}
		}else{
			return false;
		}
	}

	public function hora_hasta($horaDesde,$idProcedimiento,$fecha,$idMedico){
		$fecha   =  date('Y-m-d'  , strtotime($fecha)   );
		$incrementosHoras = 5;  //Incrementos de 15 minutos entre cada hora
		$horasDisponibles =  array();

		$horasHorarioMedico = $this->obtenerHoraInicioFinHorario($idMedico , $fecha); //Obtiene hora de inicio y hora fin de horario

		if($horasHorarioMedico[0]['horaInicio'] != NULL  &&  $horasHorarioMedico[0]['horaFin']!=NULL ){
			$periodoMinimoProcedimiento =  $this->obtenerPeriodoProcedimiento($idProcedimiento);  //Periodo minimo establecido para el procedimiento
			$horaInicioHorario = $horasHorarioMedico[0]['horaInicio'].":00:00";
			$horaFinalizacionHorario =  $horasHorarioMedico[0]['horaFin'].":00:00";   //VALIDAR POR FAVOR QUE EL MEDICO TENGA HORARIO DISPONIBLE
			$horaFinalizacionHorario = (new DateTime($horaFinalizacionHorario))->modify("+1 hours")->format("H:i:s");
			$horaDesde = $this->sumarHoras(   $horaDesde , $periodoMinimoProcedimiento     );
			$horario = $this->obtenerHorarioMedicoHasta(   $idMedico , $fecha , $horaDesde  , $horaFinalizacionHorario );  //Horario del medico para la fecha especificada , desde una hora especificada
			$disponibilidad = true;

			foreach ($horario as $key => $hora) {
				$inicio = (  $key== 0  ?  intval(date("i",  strtotime($horaDesde))) :  0   );
				for(  $i = $inicio ;  $i <60 ;  ($i+= $incrementosHoras)    ){ //Iteraciones por hora
					$horaIteracion   =  $hora['hora'].":".(   ($i < 10 ) ? "0".$i : $i  ).":00";

					$disponibilidad = $this->verificarDisponibilidadHorario2( 0 ,   $horaIteracion , $fecha ,  $idMedico ,"00:00:00" , 0  );  //Validacion de disponibilidad del horario para la hora exacta
					if( $disponibilidad  &&   ( $horaIteracion <= $horaFinalizacionHorario)  ){
						$horasDisponibles[] =  $horaIteracion;
					}else{
						break;
					}
				}
				if(!$disponibilidad){
					break;
				}
			}
		}
		return $horasDisponibles;
	}

	public function obtenerHorarioMedicoHasta($idMedico,$fecha,$horaDesde,$horaFinalizacionHorario){
		$horaInicio =  date("H" ,  strtotime($horaDesde)   );
		$horaFin =  date("H" , strtotime($horaFinalizacionHorario)   );
		$sqlHorarioMedico = "SELECT * , TIME_FORMAT( ADDTIME( CONCAT(hora,':00:00' ), '01:00:00' ) ,'%H') AS limite  FROM horas 
        LEFT JOIN medicos_horarios
        ON (medicos_horarios.idhorario =  horas.idhora AND medicos_horarios.fecha = '$fecha' AND  medicos_horarios.estado = 0 AND  medicos_horarios.idmedico =$idMedico )
        WHERE horas.hora BETWEEN $horaInicio AND $horaFin  ";
		$horarioMedico = $this->db->query($sqlHorarioMedico)->result_array();

		return $horarioMedico;
	}

	public function nueva_cita($user,$pass,$ide,$fecha_reserva,$horas,$horasFin,$idPeriodoProcedimientoMedico,$medico,$sucursal,$observaciones){
		$hora_reserva = $horas;
		$hora_reserva = date('H:i:s', strtotime($hora_reserva));
		$hora_reservanew = date('H:i:s', strtotime($horasFin));  //Hora fin establecida desde formulario
		$start = $fecha_reserva . " " . $hora_reserva;
		$start2 = $fecha_reserva . " " . $hora_reservanew;
		$fechactual = date("d-m-Y");
		$fechacomp = $fecha_reserva;
		$fechahora = date('Y-m-d H:i:s', strtotime($start));
		$fechahorafin = date('Y-m-d H:i:s', strtotime($start2));
		$res_fichas_existe = 0;

		$idProcedimientoMedico =  $idPeriodoProcedimientoMedico;
		//$idProcedimientoMedico = $this->obtenerProcedimientoMedico($idPeriodoProcedimientoMedico);

		//CONDICION PARA RESERVAS CITAS
		$reservaCitas = "  '$horas'   BETWEEN   ADDTIME(  TIME(fechahora) , '00:01:00'     )  AND   SUBTIME( TIME(fechahorafin)  , '00:01:00'    )  OR
   '$horasFin'   BETWEEN    ADDTIME(  TIME(fechahora) , '00:01:00'     )  AND   SUBTIME( TIME(fechahorafin)  , '00:01:00'    )   OR
   ADDTIME(  TIME(fechahora) , '00:01:00'     )   BETWEEN   '$horas'  AND '$horasFin'  OR
   SUBTIME( TIME(fechahorafin)  , '00:01:00'    )  BETWEEN   '$horas'  AND '$horasFin'  ";


//CONDICION PARA RESERVAS FICHAS
		$reservaFichas = "  '$horas'  BETWEEN ADDTIME(  hora_cita , '00:01:00'   ) AND SUBTIME(   hora_fin  , '00:01:00'     )   OR
    '$horasFin'   BETWEEN   ADDTIME(  hora_cita , '00:01:00'   ) AND SUBTIME(   hora_fin  , '00:01:00'     )   OR
    ADDTIME(  hora_cita , '00:01:00'   )   BETWEEN   '$horas'  AND '$horasFin'  OR
    SUBTIME(  hora_fin , '00:01:00'  )  BETWEEN   '$horas'  AND '$horasFin'      ";

//SI LA RESERVA EXISTE
		$query1 = $this->db->query("SELECT COUNT(*) AS num FROM reserva_citas WHERE idexpediente='".$ide."' AND idmedico='".$medico."' AND fechahora='".$fechahora."' AND   ( status=1 OR status=2 ) AND  (  $reservaCitas )   ");
		foreach ($query1->result_array() as $row1) {
			$res_existe = $row1['num'];
		}
		$query2 = $this->db->query("SELECT COUNT(*) AS num FROM reserva_citas WHERE idmedico='".$medico."' AND fechahora='".$fechahora."' AND ( status=1  OR status=2 )   AND  (   $reservaCitas  )   ");
		foreach ($query2->result_array() as $row2) {
			$horario_existe = $row2['num'];
		}
		$query3 = $this->db->query("
			SELECT idusuario FROM usuarios WHERE usuario = '$user' AND password = '$pass'
		");
		foreach ($query3->result_array() as $row3) {
			$usuario = $row3['idusuario'];
		}
		/*FIN RESERVAS FICHAS*/
		/*  if(strtotime($fechacomp)<strtotime($fechactual)) {  return 2;  } else */
		if($res_existe>0){
			return 2;
		} else if ($horario_existe>0){
			return 3;
		}else {
			$fecha_creacion = date('Y-m-d');
				$hora_creacion = date('H:i:s');
				$hora_limite = date('H:i:s',strtotime( '+4 hours',strtotime($hora_creacion)));
				$cita_urgente = ($horas <= $hora_limite && $fecha_reserva == $fecha_creacion) ? 1 : 0;
				$data = array(
					'idexpediente' => $ide,
					'fechahora' => $fechahora,
					'fechahorafin' => $fechahorafin,
					'fecha' => date("Y-m-d",strtotime($fecha_reserva)),
					'hora' => date("H:i:s",strtotime($hora_reserva)),
					'idmedico' => $medico,
					'observaciones' => $observaciones,
					'status' => 2,
					'fecha_creacion' => date("Y-m-d"),
					'hora_creacion'=> date("H:i:s"),
					'idusuario' => $usuario,
					'idsucursal' => $sucursal,
					'id_procedimiento_medico'=>  $idProcedimientoMedico ,
					'origen' => 'Sistema App Crear citas',
					'cita_urgente' => $cita_urgente
				);
				$this->db->insert('reserva_citas', $data);
				return 1;
		}
	}

	public function estados() {
		$estados = $this->db->query("
			SELECT id_estado as idestado, estado FROM estado_reserva
		")->result_array();
		return $estados;
	}

	public function ver_citas($idestado,$idsucursal,$idespecialidad,$idmedico,$idreservacita,$idexpediente){
		$estado='';
		if ($idestado>0){
			$estado = "AND rc.status = $idestado";
		}
		$sucursal='';
		if ($idsucursal>0){
			$sucursal = "AND rc.idsucursal = $idsucursal";
		}
		$medico='';
		if ($idmedico>0){
			$medico = "AND rc.idmedico = $idmedico";
		}
		$reserva='';
		if ($idreservacita>0){
			$reserva = "AND idreservacita = $idreservacita";
		}
		$expediente='';
		if ($idexpediente>0){
			$reserva = "AND rc.idexpediente = $idexpediente";
		}
		$json = array();
		$query = $this->db->query("
		SELECT
		  CONCAT(me.nombres, ' ', me.apellidos) AS 'nombre_medico',
		  me.backgroundColor,
		  ex.nombre_completo AS 'nombre_expediente',
		  ex.correo_electronico AS 'correo_expediente',
		  er.estado AS 'estado_cita',
		  es.id_especialidad AS 'idespecialidad',
		  es.especialidad,
		  su.idsucursal,
		  su.sucursal,
		  id_procedimiento_medico_especialidad,
		  pm.nombre_procedimiento,
		  us.nombre AS 'nombre_usuario',
		  rc.*
		FROM
		  reserva_citas rc
			INNER JOIN medicos me ON me.idmedico = rc.idmedico
			INNER JOIN especialidades es ON es.id_especialidad = me.id_especialidad
			INNER JOIN expedientes ex ON ex.idexpediente = rc.idexpediente
			INNER JOIN estado_reserva er ON er.id_estado = rc.status
			INNER JOIN sucursales su ON su.idsucursal = rc.idsucursal
			INNER JOIN procedimientos_medicos_especialidades pr ON pr.id_procedimiento_medico_especialidad = rc.id_procedimiento_medico
			INNER JOIN procedimientos_medicos pm ON pm.id_procedimiento_medico = pr.id_procedimiento_medico
			INNER JOIN usuarios us ON us.idusuario = rc.idusuario
		WHERE
		  rc.fecha >= '20210101'
		  $estado
		  $sucursal
		  $medico
		  $reserva
		  $expediente
		LIMIT 10000");
		foreach ($query->result_array() as $row) {
			$e = array();

			$e['idreservacita']       = $row['idreservacita'];
			$e['fecha']               = date("d-m-Y", strtotime($row['fecha']));
			$e['hora_inicio']         = date('H:i:s', strtotime($row['fechahora']));
			$e['hora_fin']            = date('H:i:s', strtotime($row['fechahorafin']));
			$e['idmedico']            = $row['idmedico'];
			$e['nombre_medico']       = $row['nombre_medico'];
			$e['idespecialidad']      = $row['idespecialidad'];
			$e['especialidad']        = $row['especialidad'];
			$e['id_procedimiento']    = $row['id_procedimiento_medico_especialidad'];
			$e['procedimiento']       = $row['nombre_procedimiento'];
			$e['idsucursal']          = $row['idsucursal'];
			$e['sucursal']            = $row['sucursal'];
			$e['idexpediente']        = $row['idexpediente'];
			$e['nombre_expediente']   = $row['nombre_expediente'];
			$e['correo_expediente']   = $row['correo_expediente'];
			$e['idusuario']           = $row['idusuario'];
			$e['nombre_usuario']      = $row['nombre_usuario'];
			$e['color']     		  = trim($row['backgroundColor']);
			$e['idestadocita']        = $row['status'];
			$e['estado_cita']         = $row['estado_cita'];
			$e['observaciones']       = $row['observaciones'];
			$e['origen_cita']         = $row['origen'];
			$e['fecha_creacion']      = date("d-m-Y", strtotime($row['fecha_creacion']));
			$e['hora_creacion']       = date('H:i:s', strtotime($row['hora_creacion']));
			array_push($json, $e);
		}
		return $json;
	}

	public function cambiar_estado($idreservacita,$estado) {
		$this->db->where('idreservacita', $idreservacita);
		$this->db->set('status', $estado);
		if ($this->db->update('reserva_citas')){
			return 1;
		}else{
			return 0;
		}

	}

	public function agregar_usuario($nombre,$user,$pass){
		$query=$this->db->query("SELECT COUNT(*) as num FROM usuarios WHERE usuario = '".$user."'");
		foreach ($query->result_array() as $row)
		{
			$numuser = $row['num'];
		}
		if($numuser==0) {
			$data = array(
				'usuario' => $user,
				'password' => $pass,
				'idrol' => 20,
				'nombre' => $nombre,
				'dui' => '00000000-0',
				'estado' => 1,
				'idsucursal' => 1,
				'autorizar' => 0,
				'texto_firma'=>''
			);
			if($this->db->insert('usuarios',$data)) {
				return "Usuario creado con exito";
			}
			else {
				return "Error al crear usuario";
			}
		}
		else {
			return "Usuario ya existe";
		}
	}

}
