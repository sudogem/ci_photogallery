<?php

class Album_model extends CI_Model {

  function __construct()
  {
    parent::__construct();
    $this->t_album = 'album' ;
    $this->t_user  = 'user' ;
    $this->t_photo = 'photo' ;
    $this->t_album_users = 'album_users' ;
    $this->data = new stdClass ;
    $this->user_id = getuid();
    $this->per_page = $this->config->item('per_page');
    $this->data = new stdClass;
  }

  /**
   * Find the album by id
   */
  public function find_by_id($id )
  {
    $this->db->where('id', $id);
    $query = $this->db->get( $this->t_album );
    $data = $query->row_array();
    return $data ;
  }

  public function get_album_user($album_id)
  {
    $this->db->join($this->t_album_users, sprintf("album_users.album_id = album.id and album.id = %d", $album_id));
    $this->db->join($this->t_user, sprintf("user.id = album_users.user_id"));
    $query = $this->db->get( $this->t_album );
    $data  = $query->row_array();
    return $data ;
  }
  
  /**
   * Return the first row in the album table
   */
  public function first()
  {
    $query = $this->db->get( $this->t_album );
    $data = $query->first_row();
    return $data;
  }

  /**
   * Get all the album with extra parameters
   */
  public function get_all_album($params=array())
  {
    $where = ' WHERE 1=1 ';
    $base_url = !empty($params['base_url']) ? $params['base_url'] : site_url("admin/album/index");
    $search_keyword = !empty($params['search_keyword']) ? $params['search_keyword'] : '';
    $is_paginate = !empty($params['is_paginate']) ? $params['is_paginate'] : false;
    if ($is_paginate) {
      $config['base_url'] = $base_url;
      $config['total_rows'] = $this->get_album_count();
      $config['per_page'] = $this->per_page ;
      $config['uri_segment'] = 4;
      $this->pagination->initialize($config);
      $pagination = $this->pagination->create_links();
      $cur_offset = ( $this->uri->segment( $config['uri_segment'] ) != '' ) ? (int)$this->uri->segment( $config['uri_segment'] ) : 0 ;
      $tmpcur_offset = (empty($cur_offset)) ? 0 : $config['per_page'];
      $u = ($config['per_page'] * $cur_offset) - $tmpcur_offset;
    }

    if (!empty($search_keyword)) {
      $where .= " AND album_name LIKE '%$search_keyword%' ";
    }

    if (isset($params['order_by'])) {
      $order_by = $params['order_by'];
    } else {
      $order_by = "photo.created_at desc";
    }

    if (isset($params['type']) && $params['type'] == 'public') {
        $sql = sprintf("SELECT album.*, count(photo.id) as total_photos FROM photo
                        RIGHT JOIN album ON album.id = photo.album_id
                        GROUP BY album.id
                        HAVING total_photos > 0
                        ORDER BY %s", $order_by);
        $query = $this->db->get($this->t_album);
        $query = $this->db->query($sql);
    } else {
      if (is_admin()) {
        $limit = $is_paginate ? " limit $u, $this->per_page" : "";
        $sql = sprintf("SELECT album.*, count(photo.id) as total_photos FROM photo
                        RIGHT JOIN album ON album.id = photo.album_id
                        %s
                        GROUP BY album.id
                        ORDER BY %s %s", $where, $order_by, $limit);
        $query = $this->db->query($sql);
      }
    }
    if ($query->result()) {
      return array('data' => $query->result_array(), 'pagination' => $is_paginate ? $pagination : "");
    }
    else {
      return FALSE;
    }
  }

  public function get_album_count()
  {
    if (is_admin()) {
      $query = $this->db->get($this->t_album);
      return $query->num_rows();
    } else {
      $this->db->join($this->t_album_users, sprintf("album_users.album_id = album.id and album_users.user_id = %d", $this->user_id));
      $query = $this->db->get($this->t_album);
      return $query->num_rows();
    }
  }

  public function add()
  {
    $this->data->album_name = $this->input->post('album_name');
    $this->data->album_desc = $this->input->post('album_desc');
    $created = $this->input->post('created');
    $this->data->created_at = (isset($created) && $created!= '') ? date('Y-m-d', strtotime($created)) : date('Y-m-d');
    $this->data->updated_at = (isset($created) && $created!= '') ? date('Y-m-d', strtotime($created)) : date('Y-m-d');
    $this->db->insert( $this->t_album, $this->data);
    $album_id = $this->db->insert_id();
    $this->db->insert( $this->t_album_users, array('user_id' => $this->user_id, 'album_id' => $album_id));
  }

  public function edit()
  {
    $this->data->album_name   = $this->input->post('album_name');
    $this->data->album_desc   = $this->input->post('album_desc');
    $this->data->updated_at   = date("Y-m-d") ;
    $this->db->where( array('id' => $this->input->post('id'))) ;
    $this->db->update( $this->t_album , $this->data );
  }

  public function delete($id)
  {
    $this->db->delete($this->t_album, array('id' => $id));
    $this->gallery_model->delete_by_album($id);
  }

}
